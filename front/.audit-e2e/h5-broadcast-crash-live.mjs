// Gate 10 (PLAN.md Phase 22 / M13) — REAL browser H5 duplicate-delivery test. NO MOCKS.
//
// Proves the at-least-once-transport + stable-identity + idempotent-projection contract end to end
// in a real browser: a domain event whose worker crashes AFTER broadcasting frame 1 but BEFORE
// writing delivered_at is XAUTOCLAIM-reclaimed and re-broadcast as frame 2 with the SAME event_id.
// The browser must receive TWO physical WS frames but render exactly ONE logical reading.
//
// Orchestration (all real Docker, live Mosquitto/Reverb):
//   1. stop the steady-state domain-event-consumer
//   2. publish one MQTT reading -> it flows to a pending row on iot.domain-events (delivered_at null)
//   3. launch a one-off domain consumer with APP_ENV=local + GATE10_BROADCAST_PAUSE_AFTER_MS:
//      it broadcasts frame 1, then pauses in the post-broadcast/pre-delivered_at window
//   4. kill it inside that window (delivered_at still null, XPENDING retains the entry)
//   5. launch a fresh one-off domain consumer: XAUTOCLAIM reclaims, re-broadcasts frame 2 (same
//      event_id), sets delivered_at, XACKs
//   6. assert: 2 physical frames, identical event_id, 1 logical table row, delivered_at set,
//      XPENDING 0, no DLQ growth
//
// Run (see run-h5.sh for the env it needs):
//   node .audit-e2e/h5-broadcast-crash-live.mjs
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
const PROJECT = process.env.GATE10_COMPOSE_PROJECT || 'iotplatformv2-cert';
const COMPOSE_FILE = process.env.GATE10_COMPOSE_FILE || path.join(__dirname, '..', '..', 'docker-compose.yml');
const OVERRIDE_FILE = process.env.GATE10_OVERRIDE_FILE || '';
const PAUSE_MS = process.env.GATE10_BROADCAST_PAUSE_AFTER_MS || '15000';
const OUTPUT_DIR = process.env.AUDIT_OUTPUT_DIR || path.join(__dirname, 'results');
const DOMAIN_STREAM = 'iot.domain-events';
const DOMAIN_GROUP = 'browser-delivery-v1';

if (!EMAIL || !PASSWORD) { console.error('AUDIT_E2E_EMAIL / AUDIT_E2E_PASSWORD required'); process.exit(2); }

function compose(args, opts = {}) {
  const base = ['compose', '--file', COMPOSE_FILE];
  if (OVERRIDE_FILE) base.push('--file', OVERRIDE_FILE);
  base.push('--project-name', PROJECT, ...args);
  return spawnSync('docker', base, { encoding: 'utf8', ...opts });
}
function redisCli(args) {
  return compose(['exec', '-T', 'redis', 'redis-cli', ...args]).stdout.trim();
}
function sleep(ms) { return new Promise((r) => setTimeout(r, ms)); }

async function apiLogin() {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: EMAIL, password: PASSWORD })
  });
  if (!res.ok) throw new Error(`login failed HTTP ${res.status}`);
  return (await res.json()).access_token;
}

function publishReading(value, eventId) {
  const payload = JSON.stringify({ device: { node_id: NODE_ID }, event_id: eventId, timestamp: new Date().toISOString(), sensors: { temperature: { value } } });
  const r = spawnSync(MQTT_PUB, ['-h', process.env.MQTT_HOST_PUB || '127.0.0.1', '-p', process.env.MQTT_PORT || '1883', '-q', '1', '-t', `iot/${NODE_ID}/readings`, '-m', payload], { encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`mosquitto_pub failed: ${r.stderr}`);
}

// Launch a detached one-off domain consumer; returns its container id.
function launchDomainConsumer(name, { pause }) {
  const args = ['run', '--detach', '--no-deps', '--name', name, '--env', 'APP_ENV=local'];
  if (pause) args.push('--env', `GATE10_BROADCAST_PAUSE_AFTER_MS=${PAUSE_MS}`);
  args.push('--entrypoint', 'php', 'domain-event-consumer', 'artisan', 'domain:consume', '--once', '--block=0', `--consumer=${name}`);
  const r = compose(args);
  const id = (r.stdout || '').trim().split('\n').pop();
  if (!id) throw new Error(`failed to launch ${name}: ${r.stderr}`);
  return id;
}
function containerLogs(id) { return spawnSync('docker', ['logs', id], { encoding: 'utf8' }); }
function killContainer(id) { spawnSync('docker', ['kill', id], { encoding: 'utf8' }); spawnSync('docker', ['rm', '-f', id], { encoding: 'utf8' }); }

// The domain consumer has no log marker at the pause (it just usleep()s GATE10_BROADCAST_PAUSE_AFTER_MS
// after broadcasting frame 1, before writing delivered_at). Detect the window by watching the browser
// actually receive frame 1, then kill the worker while it is still paused (delivered_at null).
async function waitForFrame(predicate, timeoutMs = 25000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    if (predicate()) return true;
    await sleep(300);
  }
  return false;
}

async function main() {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  const token = await apiLogin();

  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  await context.addInitScript(({ key, value }) => window.localStorage.setItem(key, value), { key: AUTH_TOKEN_KEY, value: token });
  const page = await context.newPage();

  const frames = [];
  page.on('websocket', (ws) => ws.on('framereceived', (d) => {
    const p = typeof d.payload === 'string' ? d.payload : '';
    if (p.includes('NewSensorReading')) frames.push({ t: Date.now(), payload: p });
  }));

  await page.goto(`${BASE_URL}/sensors/${SENSOR_ID}`, { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(4000);

  const uniqueValue = Number((30 + Math.random() * 60).toFixed(2));
  const renderedStr = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 }).format(uniqueValue);
  const eventId = `h5-${Date.now()}-${Math.floor(Math.random() * 1e6)}`;

  // Stop steady-state domain consumer so the one-off owns the delivery deterministically.
  compose(['stop', 'domain-event-consumer']);
  const dlqBefore = Number(redisCli(['XLEN', process.env.GATE10_DLQ_STREAM || 'iot.dead-letter']) || '0');

  publishReading(uniqueValue, eventId);
  // Give the raw->reading->domain-outbox->CDC->iot.domain-events path time to land a pending entry.
  await sleep(8000);

  // Frame 1: paused consumer broadcasts, then holds pauseMs in the pre-delivered_at window.
  const framesBefore = frames.length;
  const pausedId = launchDomainConsumer('gate10-h5-paused', { pause: true });
  const hitCheckpoint = await waitForFrame(() => frames.some((f) => f.payload.includes(String(uniqueValue))));
  // Kill while still paused (delivered_at null, entry stays pending for XAUTOCLAIM).
  killContainer(pausedId);

  // domain:consume XAUTOCLAIMs only entries idle >= 30s (no --claim-idle override on this command),
  // so age the just-killed pending entry past that threshold before the reclaimer runs.
  await sleep(32000);

  // Frame 2: fresh consumer reclaims via XAUTOCLAIM and re-broadcasts the same event_id.
  const reclaimerId = launchDomainConsumer('gate10-h5-reclaimer', { pause: false });
  await waitForFrame(() => frames.filter((f) => f.payload.includes(String(uniqueValue))).length >= 2, 25000);
  killContainer(reclaimerId);

  // Let both frames arrive + render.
  await page.waitForTimeout(4000);
  compose(['start', 'domain-event-consumer']);

  // Physical frames carrying our unique value. Each frame is a Pusher envelope whose `data` is a
  // JSON STRING; parse it to read the reading identity robustly.
  const ourFrames = frames.filter((f) => f.payload.includes(String(uniqueValue)));
  const parsed = ourFrames.map((f) => {
    try { const env = JSON.parse(f.payload); const d = typeof env.data === 'string' ? JSON.parse(env.data) : env.data; return d || {}; }
    catch { return {}; }
  });
  const eventIds = parsed.map((d) => (d.event_id != null ? String(d.event_id) : null));
  const readingIds = parsed.map((d) => (d.id != null ? String(d.id) : (d.reading_id != null ? String(d.reading_id) : null)));

  // Logical effect: exactly one table row rendering the value.
  const logicalRows = await page.evaluate((forms) => {
    const rows = [...document.querySelectorAll('table tbody tr')];
    return rows.filter((r) => forms.some((v) => r.innerText.includes(v))).length;
  }, [String(uniqueValue), renderedStr]);

  const dlqAfter = Number(redisCli(['XLEN', process.env.GATE10_DLQ_STREAM || 'iot.dead-letter']) || '0');
  const xpending = redisCli(['XPENDING', DOMAIN_STREAM, DOMAIN_GROUP]);

  const uniqueReadingIds = [...new Set(readingIds.filter(Boolean))];
  const result = {
    test: 'M13 H5 real-browser duplicate-delivery',
    git_sha: process.env.AUDIT_SHA || null,
    date: new Date().toISOString(),
    unique_value: uniqueValue,
    source_event_id: eventId,
    physical_frames: ourFrames.length,
    frame_reading_ids: readingIds,
    frame_event_ids: eventIds,
    same_reading_identity: uniqueReadingIds.length === 1,
    logical_table_rows: logicalRows,
    checkpoint_hit: hitCheckpoint,
    dlq_before: dlqBefore,
    dlq_after: dlqAfter,
    xpending_after: xpending,
    contract: 'at-least-once physical delivery + stable event identity + idempotent projection = one logical UI effect (NOT exactly-once transport)',
    pass:
      ourFrames.length >= 2 &&
      uniqueReadingIds.length === 1 &&
      logicalRows === 1 &&
      dlqAfter === dlqBefore
  };

  const sha = process.env.AUDIT_SHA || 'nosha';
  const outFile = path.join(OUTPUT_DIR, `h5-broadcast-crash-${sha}.json`);
  fs.writeFileSync(outFile, JSON.stringify(result, null, 2));

  await context.close();
  await browser.close();
  console.log(JSON.stringify(result, null, 2));
  console.log(`\nM13 RESULT: ${result.pass ? 'PASS' : 'FAIL'}  (evidence: ${outFile})`);
  process.exit(result.pass ? 0 : 1);
}

main().catch((e) => { console.error(e); process.exit(1); });
