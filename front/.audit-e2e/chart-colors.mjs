import { chromium, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const browser = await chromium.launch({ channel: 'chrome' });
mkdirSync('.audit-e2e/results/chart-colors', { recursive: true });
try {
  for (const width of [1440, 390]) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', msg => { if (msg.type() === 'error') console.log(msg.text()); });
    page.on('response', res => { if (res.status() >= 400) console.log(res.status(), res.url()); });
    await page.goto('http://127.0.0.1:5173/dashboard');
    const canvas = page.locator('.lab-main-chart canvas');
    try { await expect(canvas).toBeVisible({ timeout: 15000 }); }
    catch (e) {
      console.log(errors, await page.locator('body').innerText());
      throw e;
    }
    await expect.poll(() => canvas.evaluate(el => {
      const { data } = el.getContext('2d').getImageData(0, 0, el.width, el.height);
      const counts = { green: 0, amber: 0, red: 0, blue: 0 };
      for (let i = 0; i < data.length; i += 4) {
        const [r, g, b, a] = data.slice(i, i + 4);
        if (a < 10) continue;
        if (g > r + 30 && g > b + 30) counts.green++;
        if (r > 150 && g > 85 && g < r - 25 && b < g - 35) counts.amber++;
        if (r > g + 60 && r > b + 60 && g < 100) counts.red++;
        if (b > r + 60 && b > g + 40) counts.blue++;
      }
      return Object.values(counts).every(count => count > 100);
    }), { message: 'canvas must actually contain green, amber, red and blue pixels' }).toBe(true);
    await page.screenshot({ path: `.audit-e2e/results/chart-colors/dashboard-${width}.png`, fullPage: true });
    expect(errors).toEqual([]);
    console.log(`PASS ${width}px: green, amber, red zones and blue trace; no runtime errors`);
    await page.close();
  }
} finally { await browser.close(); }
