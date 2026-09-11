// Live browser check against the running Docker stack (front:5173 + real API:8000).
// Logs in via the real login form, then captures dashboard desktop (1440) + mobile (390),
// and asserts the private sensor's history loads (the new /api/sensors/{id}/series path).
import { chromium } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:5173';
const OUT = process.env.OUT_DIR || '/private/tmp/claude-501/-Users-j-rinconc-Desktop-ing-sistemas-Sistemas-Unab--iot-platform-v2/6777ffd0-7e74-450a-a45e-f616d6c81d15/scratchpad';
const EMAIL = 'admin@example.com';
const PASSWORD = 'password';

const browser = await chromium.launch();
const results = [];

async function run(label, viewport) {
  const ctx = await browser.newContext({ viewport });
  const page = await ctx.newPage();
  const apiCalls = [];
  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));
  page.on('request', (r) => { const u = r.url(); if (u.includes('/api/')) apiCalls.push(new URL(u).pathname + (new URL(u).search)); });

  // Login
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 30000 });
  await page.fill('input[type="email"], input[name="email"]', EMAIL);
  await page.fill('input[type="password"], input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 20000 }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
  await page.waitForTimeout(2500);

  // Add a graph for the PRIVATE sensor to exercise the authenticated /api/sensors/{id}/series path.
  let addedPrivate = false;
  try {
    await page.getByRole('button', { name: /Agregar gr[aá]fica|Gr[aá]fica/i }).first().click();
    await page.waitForTimeout(500);
    const selects = page.locator('dialog select, form select');
    if (await selects.count() >= 2) {
      const sensorSelect = selects.nth(1);
      const opts = await sensorSelect.locator('option').allInnerTexts();
      const doIdx = opts.findIndex((t) => /dissolved|oxygen|ox[íi]geno/i.test(t));
      if (doIdx >= 0) await sensorSelect.selectOption({ index: doIdx });
      else await sensorSelect.selectOption({ value: '2' }).catch(() => {});
      await page.getByRole('button', { name: /Agregar gr[aá]fica/i }).last().click();
      await page.waitForTimeout(2500);
      addedPrivate = true;
    }
  } catch { /* best-effort dialog flow */ }

  const shot = `${OUT}/live-${label}.png`;
  await page.screenshot({ path: shot, fullPage: true });

  const privateSeriesCalls = apiCalls.filter((u) => /\/api\/sensors\/\d+\/series/.test(u));
  const publicSeriesCalls = apiCalls.filter((u) => /\/api\/public\/graph\/sensors\/\d+\/series/.test(u));
  const bodyText = await page.locator('body').innerText().catch(() => '');

  results.push({
    label,
    viewport,
    shot,
    url: page.url(),
    addedPrivate,
    errors: errors.length,
    errorSample: errors.slice(0, 3),
    privateSeriesCalls: privateSeriesCalls.length,
    publicSeriesCalls: publicSeriesCalls.length,
    mentionsDissolvedOxygen: /dissolved|oxygen|ox[íi]geno/i.test(bodyText),
    apiSample: apiCalls.slice(0, 20)
  });
  await ctx.close();
}

await run('dashboard-desktop-1440', { width: 1440, height: 900 });
await run('dashboard-mobile-390', { width: 390, height: 844 });

await browser.close();
console.log(JSON.stringify(results, null, 2));
