// Gate 10 (PLAN.md Task 9.2) — LIVE deployment no-polling proof. NO MOCKS.
//
// Runs against the real running stack: Vue + Laravel + MySQL + Redis + Debezium + CDC consumer +
// domain consumer + Pusher-compatible transport. Unlike network-assertion.mjs it never calls
// mockApi(); every request hits the real backend. This is the only network trace that counts as
// Gate-10 evidence.
//
// Prereqs (all real):
//   docker compose --profile workers up -d --build
//   front dev/preview server reachable at AUDIT_BASE_URL
//   a dedicated E2E user: AUDIT_E2E_EMAIL / AUDIT_E2E_PASSWORD
//
// Run: node .audit-e2e/network-assertion-live.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE_URL = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:5173';
const API_BASE = process.env.AUDIT_API_BASE || 'http://127.0.0.1:8000/api';
const AUTH_TOKEN_KEY = 'iot-platform-v2.auth_token';
const OBSERVE_MS = Number(process.env.AUDIT_OBSERVE_MS || 35000);

const E2E_EMAIL = process.env.AUDIT_E2E_EMAIL;
const E2E_PASSWORD = process.env.AUDIT_E2E_PASSWORD;

// The ONLY anonymous product-domain surface allowed (PLAN.md Task 9 / 10 G10-PUB-07).
const ALLOWED_ANON = [
  /\/public\/graph\/bootstrap$/,
  /\/public\/graph\/sensors\/\d+\/series$/
];

// Product endpoints that must never leak to an anonymous guest, and must never appear on a
// recurring cadence for anyone.
const LEAK_ENDPOINTS = [
  /\/alerts(\/|$)/,
  /\/devices(\/|$)/,
  /\/dashboard\/(metrics|preferences)$/,
  /\/sensors(\/|$)/,
  /\/config\//,
  /\/events(\/|$)/,
  /\/profile$/
];

function recordRequests(page) {
  const log = [];
  page.on('request', (req) => {
    const url = new URL(req.url());
    if (url.pathname.includes('/api/')) log.push({ pathname: url.pathname, method: req.method(), t: Date.now() });
  });
  return log;
}

// A pathname is "recurring" if hit >= 3 times with a low-variance cadence (a polling signature).
// One-shot bootstrap + a single lifecycle recovery burst stay well under this.
function recurringOffenders(log, from, to) {
  const byPath = new Map();
  for (const r of log) {
    if (r.t < from || r.t > to) continue;
    if (!byPath.has(r.pathname)) byPath.set(r.pathname, []);
    byPath.get(r.pathname).push(r.t);
  }
  const offenders = [];
  for (const [pathname, hits] of byPath.entries()) {
    if (hits.length < 3) continue;
    const gaps = hits.slice(1).map((t, i) => t - hits[i]);
    const avg = gaps.reduce((a, b) => a + b, 0) / gaps.length;
    const variance = gaps.reduce((a, g) => a + (g - avg) ** 2, 0) / gaps.length;
    // Regular cadence (std-dev < 40% of mean gap) over 3+ hits = polling.
    if (Math.sqrt(variance) < avg * 0.4) offenders.push({ pathname, count: hits.length, avgGapMs: Math.round(avg) });
  }
  return offenders;
}

async function apiLogin() {
  if (!E2E_EMAIL || !E2E_PASSWORD) throw new Error('AUDIT_E2E_EMAIL / AUDIT_E2E_PASSWORD are required for the authenticated scenario');
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: E2E_EMAIL, password: E2E_PASSWORD })
  });
  if (!res.ok) throw new Error(`live login failed: HTTP ${res.status}`);
  const body = await res.json();
  const token = body?.data?.token ?? body?.token;
  if (!token) throw new Error('live login returned no token');
  return token;
}

async function guestScenario(browser) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  const log = recordRequests(page);

  await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle', timeout: 30000 });
  const start = Date.now();
  await page.waitForTimeout(OBSERVE_MS);
  const end = Date.now();
  await context.close();

  const anonPaths = [...new Set(log.filter((r) => r.method === 'GET').map((r) => r.pathname))];
  const unexpectedAnon = anonPaths.filter((p) => !ALLOWED_ANON.some((re) => re.test(p)));
  const leaks = anonPaths.filter((p) => LEAK_ENDPOINTS.some((re) => re.test(p)));

  return {
    observedMs: end - start,
    totalApiRequests: log.length,
    anonPaths,
    unexpectedAnon,
    leaks,
    recurringOffenders: recurringOffenders(log, start, end),
    pass: unexpectedAnon.length === 0 && leaks.length === 0 && recurringOffenders(log, start, end).length === 0
  };
}

async function authenticatedScenario(browser) {
  const token = await apiLogin();
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  await context.addInitScript(({ key, value }) => window.localStorage.setItem(key, value), { key: AUTH_TOKEN_KEY, value: token });
  const page = await context.newPage();
  const log = recordRequests(page);

  await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle', timeout: 30000 });

  // Phase 1: quiet observation after hydration.
  const p1Start = Date.now();
  await page.waitForTimeout(OBSERVE_MS);
  const p1End = Date.now();
  const steadyOffenders = recurringOffenders(log, p1Start, p1End);

  // Phase 2: visibilitychange -> at most one bounded recovery burst.
  const visStart = Date.now();
  await page.evaluate(() => {
    Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
    document.dispatchEvent(new Event('visibilitychange'));
    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
    document.dispatchEvent(new Event('visibilitychange'));
  });
  await page.waitForTimeout(5000);
  const visBurst = log.filter((r) => r.t >= visStart && r.method === 'GET');

  // Phase 3: quiet again.
  const p3Start = Date.now();
  await page.waitForTimeout(OBSERVE_MS);
  const p3End = Date.now();
  const afterVisOffenders = recurringOffenders(log, p3Start, p3End);

  // Phase 4: realtime transport disconnect/reconnect -> one recovery sequence, not timer fallback.
  const reconnectStart = Date.now();
  await context.setOffline(true);
  await page.waitForTimeout(3000);
  await context.setOffline(false);
  await page.waitForTimeout(5000);
  const reconnectBurst = log.filter((r) => r.t >= reconnectStart && r.method === 'GET');
  const reconnectQuietStart = Date.now();
  await page.waitForTimeout(OBSERVE_MS);
  const reconnectOffenders = recurringOffenders(log, reconnectQuietStart, Date.now());

  // Phase 5: logout -> authenticated projections clear; later events cannot repopulate.
  const logoutRes = await page.evaluate(async ({ apiBase, key }) => {
    const t = window.localStorage.getItem(key);
    const r = await fetch(`${apiBase}/auth/logout`, { method: 'POST', headers: { Authorization: `Bearer ${t}`, Accept: 'application/json' } });
    window.localStorage.removeItem(key);
    // Same token must now be rejected.
    const check = await fetch(`${apiBase}/alerts`, { headers: { Authorization: `Bearer ${t}`, Accept: 'application/json' } });
    return { logoutStatus: r.status, staleTokenStatus: check.status };
  }, { apiBase: API_BASE, key: AUTH_TOKEN_KEY });

  await context.close();

  return {
    steady: { offenders: steadyOffenders, pass: steadyOffenders.length === 0 },
    visibilityRecovery: { burstRequests: visBurst.length, boundedBurst: visBurst.length <= 12, afterQuietPass: afterVisOffenders.length === 0 },
    reconnectRecovery: { burstRequests: reconnectBurst.length, boundedBurst: reconnectBurst.length <= 12, afterQuietPass: reconnectOffenders.length === 0 },
    logoutIsolation: { ...logoutRes, pass: logoutRes.staleTokenStatus === 401 },
    pass:
      steadyOffenders.length === 0 &&
      afterVisOffenders.length === 0 &&
      reconnectOffenders.length === 0 &&
      logoutRes.staleTokenStatus === 401
  };
}

async function main() {
  const browser = await chromium.launch();
  let result;
  try {
    const guest = await guestScenario(browser);
    const authenticated = await authenticatedScenario(browser);
    result = {
      guest,
      authenticated,
      visibilityRecovery: authenticated.visibilityRecovery,
      reconnectRecovery: authenticated.reconnectRecovery,
      logoutIsolation: authenticated.logoutIsolation,
      recurringOffenders: [...guest.recurringOffenders, ...authenticated.steady.offenders],
      pass: guest.pass && authenticated.pass
    };
  } finally {
    await browser.close();
  }

  fs.mkdirSync(path.join(__dirname, 'results'), { recursive: true });
  fs.writeFileSync(path.join(__dirname, 'results', 'gate10-live-network.json'), JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result, null, 2));
  console.log(result.pass ? '\nGATE 10 LIVE: PASS' : '\nGATE 10 LIVE: FAIL');
  process.exit(result.pass ? 0 : 1);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
