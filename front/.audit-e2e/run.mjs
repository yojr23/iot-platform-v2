// Gate 0 Playwright runner for audit.md. Sandbox-only, not committed.
// Usage: node run.mjs --route=/dashboard --role=guest --viewport=390x844 --mode=normal --name=dashboard-guest-390
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { installAuth, mockApi } from './fixtures.mjs';

const PLAYWRIGHT_PATH = 'C:/Users/jvrincon/AppData/Roaming/npm/node_modules/@playwright/cli/node_modules/playwright';
const { chromium } = await import(pathToFileURL(path.join(PLAYWRIGHT_PATH, 'index.mjs')).href);

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE_URL = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:5173';

function arg(name, fallback) {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

const route = arg('route', '/dashboard');
const role = arg('role', 'guest');
const mode = arg('mode', 'normal');
const viewportArg = arg('viewport', '390x844');
const name = arg('name', `${route.replace(/\W+/g, '_')}-${role}-${viewportArg}`);
const [width, height] = viewportArg.split('x').map(Number);

const resultsDir = path.join(__dirname, 'results');
fs.mkdirSync(resultsDir, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width, height } });
const page = await context.newPage();

const consoleErrors = [];
const pageErrors = [];
const failedRequests = [];

page.on('console', (msg) => {
  if (msg.type() === 'error') consoleErrors.push(msg.text());
});
page.on('pageerror', (err) => pageErrors.push(String(err)));
page.on('requestfailed', (req) => {
  failedRequests.push({ url: req.url(), method: req.method(), failure: req.failure()?.errorText });
});
page.on('response', (res) => {
  if (res.status() >= 400 && res.url().includes('/api/')) {
    failedRequests.push({ url: res.url(), method: res.request().method(), status: res.status() });
  }
});

await installAuth(page, role);
await mockApi(page, { role, mode });

let navError = null;
try {
  await page.goto(`${BASE_URL}${route}`, { waitUntil: 'networkidle', timeout: 20000 });
  await page.waitForTimeout(500);
} catch (err) {
  navError = String(err);
}

const overflow = navError
  ? null
  : await page.evaluate(() => ({
      innerWidth: window.innerWidth,
      clientWidth: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      hasOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
    }));

const heading = navError
  ? null
  : await page.evaluate(() => document.querySelector('h1,h2')?.textContent?.trim() || null);

const finalUrl = navError ? null : page.url();

const screenshotPath = path.join(resultsDir, `${name}.png`);
if (!navError) {
  await page.screenshot({ path: screenshotPath, fullPage: true });
}

const summary = {
  name,
  route,
  role,
  mode,
  viewport: { width, height },
  finalUrl,
  heading,
  overflow,
  consoleErrors,
  pageErrors,
  failedRequests,
  navError,
  screenshot: navError ? null : screenshotPath
};

fs.writeFileSync(path.join(resultsDir, `${name}.json`), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary));

await browser.close();
