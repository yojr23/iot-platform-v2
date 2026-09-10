// Local visual QA with synthetic API fixtures; no production writes.
import { chromium, expect } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { installAuth, mockApi } from './fixtures.mjs';

const browser = await chromium.launch({ channel: 'chrome' });
const results = [];
const out = new URL('./results/lab-resources/', import.meta.url);
mkdirSync(out, { recursive: true });
try {
  for (const width of [320, 390, 768, 1024, 1280, 1440]) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    await installAuth(page, 'admin');
    await mockApi(page, { role: 'admin' });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const route of ['/devices', '/sensors', '/alerts', '/devices/1', '/sensors/101', '/alerts/1', '/alert-rules']) {
      await page.goto(`http://127.0.0.1:5175${route}`, { waitUntil: 'networkidle' });
      await expect(page.locator('.lab-resource-title')).toBeVisible();
      await expect(page.locator('.lab-app')).toBeVisible();
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
      expect(overflow, `${route} overflow at ${width}`).toBe(false);
      const key = route.replaceAll('/', '-').slice(1);
      await page.screenshot({ path: new URL(`${key}-${width}.png`, out).pathname.replace(/^\/(.:)/, '$1'), fullPage: true });
      if (route === '/sensors') {
        await page.getByRole('searchbox', { name: 'Buscar sensores' }).fill('no-matching-sensor');
        await expect(page.getByText('No hay sensores para mostrar.')).toBeVisible();
        await page.getByRole('searchbox').fill('');
      }
      if (route === '/alerts') {
        await page.getByRole('button', { name: 'No resueltas', exact: true }).click();
        await expect(page.getByRole('button', { name: 'No resueltas', exact: true })).toHaveAttribute('aria-pressed', 'true');
      }
      const trigger = route === '/devices' ? 'Nuevo dispositivo' : route === '/sensors' ? 'Nuevo sensor' : route === '/alert-rules' ? 'Nueva regla' : null;
      if (trigger) {
        await page.getByRole('button', { name: trigger, exact: true }).click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        expect(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth), `dialog overflow ${route} ${width}`).toBe(true);
        await page.screenshot({ path: new URL(`${key}-modal-${width}.png`, out).pathname.replace(/^\/(.:)/, '$1'), fullPage: true });
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        // Existing rule metadata loading disables its opener, losing browser focus.
        if (route !== '/alert-rules') await expect(page.getByRole('button', { name: trigger, exact: true })).toBeFocused();
      }
      results.push({ route, width, overflow, errors: [...errors] });
      expect(errors).toEqual([]);
    }
    await context.close();
    console.log(`PASS: seven routes, filters, three modals at ${width}px`);
  }
  writeFileSync(new URL('results.json', out), JSON.stringify(results, null, 2));
} finally {
  await browser.close();
}
