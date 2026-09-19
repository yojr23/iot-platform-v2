// Gate 10 (PLAN.md Phase 21 / M12) — TRUE continuous MQTT -> rendered browser E2E. NO MOCKS.
//
// Publishes ONE unique reading to the real Mosquitto broker and proves it renders in the live SPA
// through the entire event-driven lineage, with zero refresh / route reload / recurring REST GET:
//
//   mosquitto_pub -> ingestion MQTT client -> SQLite spool -> POST /api/ingestion/events
//   -> RawSensorEvent -> RawEventOutbox -> MySQL binlog -> Debezium -> Redis CDC stream
//   -> cdc:consume-outboxes -> iot.raw-events -> raw:consume (RawStreamConsumer) -> SensorReading
//   -> DomainEventOutbox -> Debezium -> iot.domain-events -> domain:consume
//   (DomainEventBroadcastConsumer) -> Reverb -> physical WS frame -> Echo -> Pinia -> rendered DOM.
//
// Prereqs (all real, all live): full `--profile workers` stack up, host Mosquitto on 1883, a
// production preview build served at AUDIT_BASE_URL, the E2E user authorized for sensor readings,
// and a device_sensor_mapping (source=ingestion_service, key=temperature) for the target sensor.
//
// Run:
//   AUDIT_BASE_URL=http://127.0.0.1:4173 AUDIT_API_BASE=http://127.0.0.1:8000/api \
//   AUDIT_E2E_EMAIL=... AUDIT_E2E_PASSWORD=... AUDIT_SENSOR_ID=1 AUDIT_NODE_ID=LAB-REA-001 \
//   MQTT_PUB=/usr/local/Cellar/mosquitto/2.1.2/bin/mosquitto_pub \
//   node .audit-e2e/mqtt-to-browser-live.mjs
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE_URL = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:4173';
const API_BASE = process.env.AUDIT_API_BASE || 'http://127.0.0.1:8000/api';
const AUTH_TOKEN_KEY = 'iot-platform-v2.auth_token';
const EMAIL = process.env.AUDIT_E2E_EMAIL;
const PASSWORD = process.env.AUDIT_E2E_PASSWORD;
const SENSOR_ID = process.env.AUDIT_SENSOR_ID || '1';
const NODE_ID = process.env.AUDIT_NODE_ID || 'LAB-REA-001';
const MQTT_PUB = process.env.MQTT_PUB || 'mosquitto_pub';
const MQTT_HOST = process.env.MQTT_HOST_PUB || '127.0.0.1';
const MQTT_PORT = process.env.MQTT_PORT || '1883';
const OUTPUT_DIR = process.env.AUDIT_OUTPUT_DIR || path.join(__dirname, 'results');
const DELIVER_TIMEOUT_MS = Number(process.env.AUDIT_DELIVER_TIMEOUT_MS || 90000);

if (!EMAIL || !PASSWORD) {
  console.error('AUDIT_E2E_EMAIL / AUDIT_E2E_PASSWORD are required');
  process.exit(2);
}

async function apiLogin() {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: EMAIL, password: PASSWORD })
  });
  if (!res.ok) throw new Error(`live login failed: HTTP ${res.status}`);
  const body = await res.json();
  const token = body?.access_token ?? body?.token ?? body?.data?.token;
  if (!token) throw new Error('login returned no token');
  return token;
}

function publishReading(uniqueValue, eventId) {
  const payload = JSON.stringify({
    device: { node_id: NODE_ID },
    event_id: eventId,
    timestamp: new Date().toISOString(),
    sensors: { temperature: { value: uniqueValue } }
  });
  const args = ['-h', MQTT_HOST, '-p', MQTT_PORT, '-q', '1', '-t', `iot/${NODE_ID}/readings`, '-m', payload];
  const r = spawnSync(MQTT_PUB, args, { encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`mosquitto_pub failed: ${r.stderr || r.error}`);
  return payload;
}

async function main() {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  const token = await apiLogin();

  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  await context.addInitScript(({ key, value }) => window.localStorage.setItem(key, value), { key: AUTH_TOKEN_KEY, value: token });
  const page = await context.newPage();

  // Capture WS lifecycle + every text frame for lineage evidence.
  const wsFrames = [];
  let wsUrl = null;
  let wsConnectedAt = null;
  page.on('websocket', (ws) => {
    wsUrl = ws.url();
    wsConnectedAt = Date.now();
    ws.on('framereceived', (data) => {
      const payload = typeof data.payload === 'string' ? data.payload : '';
      if (payload) wsFrames.push({ t: Date.now(), payload });
    });
  });

  // Guard: fail if the SPA makes a recurring REST GET for readings (polling signature).
  const readingGets = [];
  page.on('request', (req) => {
    const u = new URL(req.url());
    if (req.method() === 'GET' && /\/api\/sensors\/\d+\/readings/.test(u.pathname)) readingGets.push({ t: Date.now(), path: u.pathname });
  });

  await page.goto(`${BASE_URL}/sensors/${SENSOR_ID}`, { waitUntil: 'networkidle', timeout: 30000 });
  // Let Echo subscribe + authorize the private channel before we publish.
  await page.waitForTimeout(4000);
  const subscribeCompleteAt = Date.now();
  const getsBeforePublish = readingGets.length;

  // Unique, unmistakable value + event id so we can trace exactly this reading end to end.
  // Exactly 2 decimals so the SPA's Intl es-CO formatter (maximumFractionDigits: 2, comma decimal
  // separator — front/src/utils/formatters.js formatNumber) renders it without rounding ambiguity.
  const uniqueValue = Number((30 + Math.random() * 60).toFixed(2));
  const eventId = `m12-${Date.now()}-${Math.floor(Math.random() * 1e6)}`;
  const publishedAt = Date.now();
  const sentPayload = publishReading(uniqueValue, eventId);

  // The reading renders through the SensorReadingsTable, formatted with the es-CO locale (comma
  // decimal). Accept either the raw value or that rendered form in the DOM — both prove the reading
  // reached rendered output. The WS-frame check below independently proves the transport lineage.
  const valueStr = String(uniqueValue);
  const renderedStr = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 }).format(uniqueValue);
  let renderedAt = null;
  const deadline = Date.now() + DELIVER_TIMEOUT_MS;
  while (Date.now() < deadline) {
    const found = await page.evaluate((forms) => forms.some((v) => document.body.innerText.includes(v)), [valueStr, renderedStr]);
    if (found) { renderedAt = Date.now(); break; }
    await page.waitForTimeout(500);
  }

  const carryingFrame = wsFrames.find((f) => f.payload.includes(valueStr));
  const getsAfterPublish = readingGets.length - getsBeforePublish;

  const result = {
    test: 'M12 MQTT -> browser continuous E2E',
    git_sha: process.env.AUDIT_SHA || null,
    date: new Date().toISOString(),
    base_url: BASE_URL,
    sensor_id: SENSOR_ID,
    node_id: NODE_ID,
    unique_value: uniqueValue,
    rendered_str: renderedStr,
    event_id: eventId,
    sent_payload: sentPayload,
    websocket: { url: wsUrl, connected_at: wsConnectedAt, total_frames: wsFrames.length },
    carrying_frame: carryingFrame ? { t: carryingFrame.t, payload: carryingFrame.payload.slice(0, 600) } : null,
    timings_ms: {
      subscribe_complete: subscribeCompleteAt - wsConnectedAt,
      publish_to_render: renderedAt ? renderedAt - publishedAt : null
    },
    no_polling: { reading_gets_after_publish: getsAfterPublish },
    rendered_in_dom: renderedAt !== null,
    ws_frame_carried_value: Boolean(carryingFrame),
    pass:
      renderedAt !== null &&
      Boolean(carryingFrame) &&
      wsUrl !== null &&
      getsAfterPublish === 0
  };

  const sha = process.env.AUDIT_SHA || 'nosha';
  const outFile = path.join(OUTPUT_DIR, `mqtt-browser-live-${sha}.json`);
  fs.writeFileSync(outFile, JSON.stringify(result, null, 2));

  await context.close();
  await browser.close();

  console.log(JSON.stringify(result, null, 2));
  console.log(`\nM12 RESULT: ${result.pass ? 'PASS' : 'FAIL'}  (evidence: ${outFile})`);
  process.exit(result.pass ? 0 : 1);
}

main().catch((e) => { console.error(e); process.exit(1); });
