// Stage 0.8 (PLAN.md) — permanent no-polling network assertion.
//
// audit.md §7 / §21: observe network for >= 3x the longest current recurring-timer interval
// and assert there is no recurring latest-state REST traffic. Today this MUST fail (RED) —
// AppLayout.vue's 10s active-alerts timer and SensorMonitorBoard.vue's ~2s latest-reading
// poll are both still present. That red result is the correct, expected Stage 0 baseline,
// not a harness bug. After Stages 6/7 delete those timers, this same script should go green.
//
// Run: node .audit-e2e/network-assertion.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { installAuth, mockApi } from './fixtures.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE_URL = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:5173';

// Longest current recurring timer per audit.md §7 is AppLayout's 10,000ms active-alerts
// poll; observe >= 3x that window.
const LONGEST_TIMER_MS = 10000;
const OBSERVE_MS = LONGEST_TIMER_MS * 3 + 3000; // headroom for the initial one-shot burst on load

// One-shot on load / user action, explicitly allowed (audit.md §21: "Keep one-shot history,
// CRUD and resume commands allowed").
const ALLOWED_ONE_SHOT = [
  /\/auth\/me$/,
  /\/dashboard\/public$/,
  /\/dashboard\/preferences$/,
  /\/config\/public$/
];

function isRecurring(hits, windowMs) {
  // 3+ hits of the exact same endpoint inside the observation window is a polling signature;
  // one-shot mount/CRUD calls should show at most 1-2 hits (e.g. one retry).
  return hits.length >= 3;
}

async function main() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();

  await installAuth(page, 'user');
  await mockApi(page, { role: 'user', mode: 'normal' });

  const requestLog = [];
  page.on('request', (req) => {
    const url = new URL(req.url());
    if (url.pathname.startsWith('/api/')) {
      requestLog.push({ pathname: url.pathname, t: Date.now() });
    }
  });

  await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle', timeout: 20000 });

  const observeStart = Date.now();
  console.log(`Observing /api/* traffic for ${OBSERVE_MS}ms (>= 3x the ${LONGEST_TIMER_MS}ms AppLayout active-alerts timer)...`);
  await page.waitForTimeout(OBSERVE_MS);
  const observeEnd = Date.now();

  await browser.close();

  const byPath = new Map();
  requestLog
    .filter((r) => r.t >= observeStart)
    .forEach((r) => {
      if (!byPath.has(r.pathname)) byPath.set(r.pathname, []);
      byPath.get(r.pathname).push(r.t);
    });

  const offenders = [];
  for (const [pathname, hits] of byPath.entries()) {
    const allowed = ALLOWED_ONE_SHOT.some((re) => re.test(pathname));
    if (!allowed && isRecurring(hits, OBSERVE_MS)) {
      const gaps = hits.slice(1).map((t, i) => t - hits[i]);
      offenders.push({ pathname, count: hits.length, avgGapMs: Math.round(gaps.reduce((a, b) => a + b, 0) / gaps.length) });
    }
  }

  const summary = {
    observedMs: observeEnd - observeStart,
    longestKnownTimerMs: LONGEST_TIMER_MS,
    totalApiRequests: requestLog.length,
    recurringOffenders: offenders,
    pass: offenders.length === 0
  };

  fs.mkdirSync(path.join(__dirname, 'results'), { recursive: true });
  fs.writeFileSync(path.join(__dirname, 'results', 'network-assertion.json'), JSON.stringify(summary, null, 2));

  console.log(JSON.stringify(summary, null, 2));

  if (!summary.pass) {
    console.log(
      '\nRED (EXPECTED at Stage 0): recurring /api/* traffic detected — polling has not been '
      + 'removed yet (Stages 6/7 delete these timers). This is the correct baseline, not a bug.'
    );
  } else {
    console.log('\nGREEN: no recurring latest-state /api/* traffic observed.');
  }

  process.exit(summary.pass ? 0 : 1);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
