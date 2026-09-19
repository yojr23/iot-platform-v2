// Gate 9 (PLAN.md M16) — production-build desktop+mobile screenshot capture against the LIVE stack.
// Not a pass/fail oracle: it captures PNGs at every width/route/role for manual inspection, and
// auto-flags hard failures it CAN detect programmatically (document horizontal overflow, console
// errors, page errors). Manual review of the PNGs is still required per the plan.
//
// Run:
//   AUDIT_BASE_URL=http://127.0.0.1:4173 AUDIT_E2E_EMAIL=... AUDIT_E2E_PASSWORD=... \
//   node .audit-e2e/production-screenshots.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE_URL = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:4173';
const API_BASE = process.env.AUDIT_API_BASE || 'http://127.0.0.1:8000/api';
const AUTH_TOKEN_KEY = 'iot-platform-v2.auth_token';
const EMAIL = process.env.AUDIT_E2E_EMAIL;
const PASSWORD = process.env.AUDIT_E2E_PASSWORD;
const OUT = process.env.AUDIT_SCREENSHOT_DIR || path.join(__dirname, 'results', 'screenshots');

const WIDTHS = [320, 360, 390, 768, 1024, 1280, 1440];
// Route list kept to what the SPA actually ships; guest sees only /dashboard.
const AUTH_ROUTES = ['/dashboard', '/devices', '/sensors', '/sensors/1', '/alerts', '/alert-rules', '/metrics', '/config', '/labs', '/sensor-types', '/device-types', '/users', '/profile'];
const GUEST_ROUTES = ['/dashboard'];

async function apiLogin() {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: EMAIL, password: PASSWORD })
  });
  if (!res.ok) throw new Error(`login failed HTTP ${res.status}`);
  return (await res.json()).access_token;
}

async function capture(context, role, routes, findings) {
  for (const width of WIDTHS) {
    const page = await context.newPage();
    await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
    for (const route of routes) {
      const consoleErrors = [];
      const pageErrors = [];
      page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
      page.on('pageerror', (e) => pageErrors.push(e.message));
      try {
        await page.goto(`${BASE_URL}${route}`, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(1200);
      } catch (e) {
        findings.push({ role, route, width, type: 'nav-error', detail: String(e).slice(0, 160) });
        continue;
      }
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
      const name = `${role}_${route.replace(/\W+/g, '-').replace(/^-|-$/g, '') || 'root'}_${width}.png`;
      const file = path.join(OUT, name);
      await page.screenshot({ path: file, fullPage: true });
      if (overflow) findings.push({ role, route, width, type: 'horizontal-overflow', file: name });
      if (consoleErrors.length) findings.push({ role, route, width, type: 'console-error', detail: consoleErrors.slice(0, 3), file: name });
      if (pageErrors.length) findings.push({ role, route, width, type: 'page-error', detail: pageErrors.slice(0, 3), file: name });
    }
    await page.close();
  }
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const findings = [];
  const browser = await chromium.launch();

  // Guest
  const guestCtx = await browser.newContext();
  await capture(guestCtx, 'guest', GUEST_ROUTES, findings);
  await guestCtx.close();

  // Authenticated standard user
  const token = await apiLogin();
  const authCtx = await browser.newContext();
  await authCtx.addInitScript(({ key, value }) => window.localStorage.setItem(key, value), { key: AUTH_TOKEN_KEY, value: token });
  await capture(authCtx, 'user', AUTH_ROUTES, findings);
  await authCtx.close();

  await browser.close();

  const summary = {
    test: 'M16 production screenshots (live stack)',
    git_sha: process.env.AUDIT_SHA || null,
    date: new Date().toISOString(),
    base_url: BASE_URL,
    widths: WIDTHS,
    routes_authenticated: AUTH_ROUTES,
    routes_guest: GUEST_ROUTES,
    screenshot_dir: OUT,
    auto_detected_findings: findings,
    note: 'PNGs require manual visual inspection; auto findings cover only overflow/console/page errors.'
  };
  const outFile = path.join(__dirname, 'results', `production-screenshots-${process.env.AUDIT_SHA || 'nosha'}.json`);
  fs.writeFileSync(outFile, JSON.stringify(summary, null, 2));
  console.log(JSON.stringify({ findings_count: findings.length, findings, summary: outFile }, null, 2));
  process.exit(findings.filter((f) => f.type === 'horizontal-overflow' || f.type === 'page-error').length ? 1 : 0);
}

main().catch((e) => { console.error(e); process.exit(1); });
