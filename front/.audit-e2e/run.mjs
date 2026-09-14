// Stage 0 (audit.md / PLAN.md) Playwright runner. Repo-owned, reproducible from a fresh clone:
// `@playwright/test` is a declared devDependency (front/package.json) — no machine-specific path.
// Usage: node run.mjs --route=/dashboard --role=guest --viewport=390x844 --mode=normal --name=dashboard-guest-390
//   --interact=device-modal|alert-rule-modal|toast|measure-buttons|diagnose-overflow (optional, default none)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { installAuth, mockApi } from './fixtures.mjs';
import { hasInteractionFailure } from './result-policy.mjs';
import { buildAuthTransitionEvidence } from './auth-transition.mjs';

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
const interact = arg('interact', 'none');
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

// --- 0.4/0.6 interaction steps -------------------------------------------------
let interaction = null;

async function runDeviceModal() {
  const trigger = page.getByRole('button', { name: 'Nuevo dispositivo' });
  if ((await trigger.count()) === 0) {
    return { skipped: 'trigger-not-found (needs admin role)' };
  }

  await trigger.click();
  const dialog = page.locator('.phase-modal-backdrop [role="dialog"]');
  await dialog.waitFor({ state: 'visible', timeout: 5000 });

  const attrs = await dialog.evaluate((el) => ({
    role: el.getAttribute('role'),
    ariaModal: el.getAttribute('aria-modal'),
    ariaLabelledby: el.getAttribute('aria-labelledby'),
    hasLabelTarget: Boolean(el.getAttribute('aria-labelledby') && document.getElementById(el.getAttribute('aria-labelledby')))
  }));

  const initialFocusInside = await page.evaluate(() => {
    const dlg = document.querySelector('.phase-modal-backdrop [role="dialog"]');
    return Boolean(dlg && dlg.contains(document.activeElement));
  });

  // Tab-wrap: Shift+Tab from the first focusable element should land on the last.
  await page.keyboard.press('Shift+Tab');
  const wrappedToLast = await page.evaluate(() => {
    const dlg = document.querySelector('.phase-modal-backdrop [role="dialog"]');
    if (!dlg) return false;
    const focusables = Array.from(
      dlg.querySelectorAll('a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')
    ).filter((el) => el.offsetParent !== null);
    return focusables.length > 0 && document.activeElement === focusables[focusables.length - 1];
  });

  await page.keyboard.press('Escape');
  await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
  const closedByEscape = (await dialog.count()) === 0 || !(await dialog.isVisible().catch(() => false));

  const returnedFocus = await page.evaluate(() => document.activeElement?.textContent?.trim() === 'Nuevo dispositivo');

  return { ...attrs, initialFocusInside, wrappedToLast, closedByEscape, returnedFocus };
}

async function runAlertRuleModal() {
  const trigger = page.getByRole('button', { name: 'Nueva regla' });
  if ((await trigger.count()) === 0) {
    return { skipped: 'trigger-not-found (needs admin role)' };
  }

  await trigger.click();
  const dialog = page.locator('.phase-modal-backdrop [role="dialog"]');
  await dialog.waitFor({ state: 'visible', timeout: 5000 });

  const attrs = await dialog.evaluate((el) => ({
    role: el.getAttribute('role'),
    ariaModal: el.getAttribute('aria-modal')
  }));

  const initialFocusInside = await page.evaluate(() => {
    const dlg = document.querySelector('.phase-modal-backdrop [role="dialog"]');
    return Boolean(dlg && dlg.contains(document.activeElement));
  });

  await page.keyboard.press('Escape');
  await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
  const closedByEscape = (await dialog.count()) === 0 || !(await dialog.isVisible().catch(() => false));
  const returnedFocus = await page.evaluate(() => document.activeElement?.textContent?.trim() === 'Nueva regla');

  return { ...attrs, initialFocusInside, closedByEscape, returnedFocus };
}

async function runToastInjection() {
  // EVENT_HANDLER_SIMULATED: no real Pusher/Redis transport exists in this environment
  // (VITE_PUSHER_APP_KEY is empty, so getEcho() returns null and the real channel listener
  // in useAlertsRealtime.js never binds). We call the exact same store action
  // (alerts.js#addRealtimeAlert) that channel.listen(ALERTS_EVENT, ...) would call on a real
  // event, via a dynamic import of the *same* ES module URL the running app already loaded
  // (Vite/browser module caching means this is the same Pinia singleton, not a second store).
  const longMessage = 'Alerta critica prolongada: Laboratorio de Instrumentacion y Control '
    + 'Ambiental de Procesos Industriales Distribuidos reporto un valor fuera de rango '
    + 'sostenido durante mas de treinta minutos consecutivos en el sensor asociado.';

  const injectResult = await page.evaluate(async (message) => {
    const mod = await import('/src/stores/alerts.js');
    const store = mod.useAlertsStore();
    store.popupEnabled = true;
    const alert = store.addRealtimeAlert({
      id: 999001,
      severity: 'danger',
      message,
      sensor_name: 'Sensor de temperatura del reactor principal de la linea de produccion',
      device_name: 'Dispositivo de monitoreo continuo',
      value: 987.65,
      unit: 'C',
      timestamp: new Date().toISOString()
    });
    return { injected: Boolean(alert), latestAlertId: store.latestAlert?.id ?? null };
  }, longMessage);

  await page.waitForTimeout(200);
  const toast = page.locator('.toast.show');
  const toastVisible = await toast.isVisible().catch(() => false);

  if (!toastVisible) {
    return { ...injectResult, toastVisible: false };
  }

  const rect = await toast.evaluate((el) => {
    const r = el.getBoundingClientRect();
    const style = window.getComputedStyle(el);
    return { x: r.x, y: r.y, width: r.width, height: r.height, cssWidth: style.width, cssMaxWidth: style.maxWidth };
  });

  const closeBtn = page.locator('.toast.show .btn-close');
  const closeRect = await closeBtn.evaluate((el) => {
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y, width: r.width, height: r.height };
  }).catch(() => null);

  const viewportWidth = await page.evaluate(() => window.innerWidth);
  const fitsViewport = rect.x >= 0 && rect.x + rect.width <= viewportWidth;
  const closeReachable = closeRect ? closeRect.x >= 0 && closeRect.x + closeRect.width <= viewportWidth : null;

  return { ...injectResult, toastVisible, rect, closeRect, viewportWidth, fitsViewport, closeReachable };
}

async function measureButtons() {
  return page.evaluate(() => {
    const nodes = Array.from(document.querySelectorAll('.btn-sm, .btn-close, .btn-group-sm > .btn, .btn-group-sm > a'));
    return nodes.slice(0, 60).map((el) => {
      const r = el.getBoundingClientRect();
      return {
        text: (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 40),
        tag: el.tagName.toLowerCase(),
        width: Math.round(r.width),
        height: Math.round(r.height),
        hidden: r.width === 0 && r.height === 0,
        meets44: r.width >= 44 && r.height >= 44,
        meets24: r.width >= 24 && r.height >= 24
      };
    });
  });
}

async function addMonitors(count) {
  const addBtn = page.getByRole('button', { name: 'Agregar grafica' });
  for (let i = 0; i < count; i += 1) {
    // eslint-disable-next-line no-await-in-loop
    await addBtn.click();
    // eslint-disable-next-line no-await-in-loop
    await page.waitForTimeout(300);
  }

  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(300);

  const overflowAfter = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
    hasOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
  }));

  const headers = await page.evaluate(() => {
    const headers = Array.from(document.querySelectorAll('.monitor-card__header'));
    return headers.map((header) => {
      const r = header.getBoundingClientRect();
      const titleEl = header.querySelector('h3');
      const groupEl = header.querySelector('.btn-group');
      const titleRect = titleEl?.getBoundingClientRect();
      const groupRect = groupEl?.getBoundingClientRect();
      const docWidth = document.documentElement.clientWidth;
      return {
        title: titleEl?.textContent?.trim().slice(0, 60) || null,
        headerRight: r.right,
        titleRight: titleRect?.right ?? null,
        groupRight: groupRect?.right ?? null,
        overlaps: Boolean(titleRect && groupRect && titleRect.right > groupRect.left),
        exceedsViewport: r.right > docWidth + 1
      };
    });
  });

  return { overflowAfter, headers };
}

// Gate 9: exercise the workspace mutations through the rendered UI. This is deliberately kept
// in the shared runner so matrix rows remain declarative rather than growing one-off scripts.
async function runDashboardLifecycle() {
  const items = page.locator('.lab-list-item');
  const before = await items.count();
  const add = page.getByRole('button', { name: 'Agregar gráfica' }).first();
  await add.click();

  const dialog = page.locator('dialog.lab-dialog');
  await dialog.waitFor({ state: 'visible', timeout: 5000 });
  const selects = dialog.locator('select');
  // The initial normal fixture preloads the first four catalog sensors. Pick the final device's
  // first sensor so this exercises a real add, rather than merely selecting an existing widget.
  const deviceValues = await selects.nth(0).locator('option').evaluateAll((options) => options.map((option) => option.value));
  const deviceId = deviceValues.at(-1);
  if (!deviceId) throw new Error('Dashboard lifecycle fixture did not expose an addable device.');
  await selects.nth(0).selectOption(deviceId);
  const sensorValues = await selects.nth(1).locator('option').evaluateAll((options) => options.map((option) => option.value));
  const sensorId = sensorValues.at(0);
  if (!sensorId) throw new Error(`Dashboard lifecycle fixture device ${deviceId} did not expose an addable sensor.`);
  await selects.nth(1).selectOption(sensorId);
  await dialog.getByRole('button', { name: 'Agregar gráfica' }).click();
  await dialog.waitFor({ state: 'hidden', timeout: 5000 });

  const afterAdd = await items.count();
  const selected = items.nth(afterAdd - 1).getByRole('button');
  await selected.click();
  const selectedPressed = await selected.getAttribute('aria-pressed');

  await page.getByRole('button', { name: 'Editar' }).click();
  const remove = page.getByRole('button', { name: /^Quitar / }).last();
  await remove.click();
  const afterRemove = await items.count();
  const undo = page.getByRole('button', { name: 'Deshacer' });
  const undoVisible = await undo.isVisible().catch(() => false);
  if (undoVisible) await undo.click();
  await page.waitForTimeout(100);
  const afterUndo = await items.count();

  return {
    before,
    afterAdd,
    selectedPressed,
    afterRemove,
    undoVisible,
    afterUndo,
    pass:
      afterAdd === before + 1 &&
      selectedPressed === 'true' &&
      afterRemove === afterAdd - 1 &&
      undoVisible &&
      afterUndo === afterAdd
  };
}

// Auth boundary proof: move through the rendered logout/login flow while keeping every API
// request mocked. The second /auth/me response is deliberately standard-user shaped so the
// store's post-login fetchUser step cannot retain the initial administrator identity.
async function runAuthTransition() {
  const startedAsAdministrator = await page.evaluate(() => {
    const account = document.querySelector('.lab-account');
    return Boolean(account && account.textContent.includes('Astra Admin'));
  });

  await page.route((url) => new URL(url).pathname === '/api/auth/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { id: 2, name: 'Astra User', email: 'user@astra.test', is_admin: false }
      })
    });
  });

  await page.locator('.lab-account').click();
  await page.waitForURL((url) => url.pathname === '/dashboard', { timeout: 5000 });
  await page.waitForFunction(() =>
    !window.localStorage.getItem('iot-platform-v2.auth_token') &&
    !document.querySelector('.lab-account'),
    { timeout: 5000 }
  );
  const clearedAuthenticatedState = await page.evaluate(() =>
    !window.localStorage.getItem('iot-platform-v2.auth_token') &&
    !document.querySelector('.lab-account')
  );

  // Follow the rendered guest control rather than forcing a document navigation. This preserves
  // the same context in which logout cleared the token and exercises the actual user journey.
  await page.locator('.lab-header a.lab-button[href="/login"]').click();
  await page.waitForURL((url) => url.pathname === '/login', { timeout: 5000 });
  await page.locator('input[name="email"]').fill('user@astra.test');
  await page.locator('input[name="password"]').fill('mocked-password');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await page.waitForURL((url) => url.pathname === '/dashboard', { timeout: 5000 });
  await page.waitForFunction(() => Boolean(document.querySelector('.lab-account')), { timeout: 5000 });
  // Route navigation completes before the dashboard's graph query. Wait for the rendered
  // monitoring surface instead of sampling an in-between loading state.
  await page.waitForFunction(() => {
    const monitor = document.querySelector('.lab-main-chart');
    const chart = document.querySelector('.lab-main-chart .sensor-chart[role="img"]');
    const ranges = document.querySelectorAll('.lab-main-chart .lab-ranges button');
    const readings = document.querySelectorAll('.lab-main-chart .lab-readings-table tbody tr');
    return Boolean(monitor && chart && ranges.length > 0 && readings.length > 0);
  }, { timeout: 5000 });

  const afterLogin = await page.evaluate(() => {
    const account = document.querySelector('.lab-account');
    const board = document.querySelector('[data-testid="sensor-monitor-board"]');
    const chartList = document.querySelector('.lab-chart-list');
    const visible = (element) => {
      if (!element) return false;
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
    };
    const monitor = document.querySelector('.lab-main-chart');
    const chart = document.querySelector('.lab-main-chart .sensor-chart[role="img"]');
    const ranges = Array.from(document.querySelectorAll('.lab-main-chart .lab-ranges button'));
    const readingRows = document.querySelectorAll('.lab-main-chart .lab-readings-table tbody tr');
    const chartSummary = chart?.getAttribute('aria-label') || '';
    const adminControls = [
      ...document.querySelectorAll([
        '.lab-actions button',
        '.lab-chart-list .lab-card-heading button',
        '.lab-add-row',
        '.lab-empty button',
        '.lab-edit-actions button',
        '.lab-undo button'
      ].join(', '))
    ];
    return {
      standardUser: Boolean(account && account.textContent.includes('Astra User')),
      isPrivateSpace: document.querySelector('.lab-mode')?.textContent.includes('Espacio privado') || false,
      monitorVisible: Boolean(board && chartList && visible(monitor) && visible(chart)),
      rangeControlsAvailable: ranges.length > 0 && ranges.every(visible),
      populatedReadings: readingRows.length > 0 && /\bmuestras\b/i.test(chartSummary),
      adminControls: adminControls.map((control) => control.textContent.trim()).filter(Boolean)
    };
  });

  const adminRoutes = [
    '/config', '/config/general', '/config/alerts', '/config/email', '/config/diagnostics',
    '/alert-rules', '/labs', '/sensor-types', '/device-types', '/users'
  ];
  const adminNavigationLeakFree = await page.evaluate((routes) => {
    const hrefs = Array.from(document.querySelectorAll('a[href]')).map((link) => link.getAttribute('href'));
    return routes.every((route) => !hrefs.includes(route));
  }, adminRoutes) && await (async () => {
    for (const adminRoute of adminRoutes) {
      // eslint-disable-next-line no-await-in-loop
      await page.goto(`${BASE_URL}${adminRoute}`, { waitUntil: 'networkidle', timeout: 20000 });
      if (new URL(page.url()).pathname === adminRoute) return false;
    }
    await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle', timeout: 20000 });
    return true;
  })();

  return buildAuthTransitionEvidence({
    startedAsAdministrator,
    clearedAuthenticatedState,
    establishedStandardUser: afterLogin.standardUser && afterLogin.isPrivateSpace,
    monitorVisible: afterLogin.monitorVisible,
    rangeControlsAvailable: afterLogin.rangeControlsAvailable,
    populatedReadings: afterLogin.populatedReadings,
    adminControlsAbsent: afterLogin.adminControls.length === 0,
    adminNavigationLeakFree
  });
}

// Gate 9 responsive assertions that are hard to infer from a screenshot alone. The checks are
// recorded for both mocked and live-browser runs; their provenance is in the matrix/report.
async function inspectResponsiveLayout() {
  return page.evaluate(() => {
    const width = document.documentElement.clientWidth;
    const height = window.innerHeight;
    const rect = (el) => {
      const r = el.getBoundingClientRect();
      return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
    };
    const visible = (el) => {
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
    };
    const toolbarActions = Array.from(document.querySelectorAll('.lab-toolbar .lab-actions > button'))
      .filter(visible)
      .map((el) => ({ text: el.textContent.trim(), ...rect(el), insideViewport: rect(el).left >= 0 && rect(el).right <= width + 1 }));
    const controls = Array.from(document.querySelectorAll('button, a.lab-button, select, input'))
      .filter(visible)
      .map((el) => ({ text: (el.getAttribute('aria-label') || el.textContent || '').trim().slice(0, 60), ...rect(el) }));
    const undersizedControls = controls.filter((control) => control.width < 44 || control.height < 44);
    const bottomNav = document.querySelector('.lab-bottom');
    const bottomNavStyle = bottomNav ? getComputedStyle(bottomNav) : null;
    const tables = Array.from(document.querySelectorAll('.lab-table-wrap')).map((wrap) => ({
      ...rect(wrap),
      clientWidth: wrap.clientWidth,
      scrollWidth: wrap.scrollWidth,
      contained: wrap.getBoundingClientRect().right <= width + 1
    }));
    const expanded = Array.from(document.querySelectorAll('.lab-main-chart')).filter(visible).length;
    const canvases = Array.from(document.querySelectorAll('canvas')).filter(visible).map((canvas) => ({
      width: canvas.width,
      height: canvas.height,
      clientWidth: canvas.clientWidth,
      clientHeight: canvas.clientHeight,
      has2dContext: Boolean(canvas.getContext('2d'))
    }));

    return {
      documentOverflow: document.documentElement.scrollWidth > width,
      toolbarActions,
      toolbarActionsInsideViewport: toolbarActions.every((action) => action.insideViewport),
      controlsCount: controls.length,
      undersizedControls,
      bottomNav: bottomNav ? {
        ...rect(bottomNav),
        position: bottomNavStyle.position,
        paddingBottom: bottomNavStyle.paddingBottom,
        insideViewport: rect(bottomNav).bottom <= height + 1
      } : null,
      tables,
      tableScrollersContained: tables.every((table) => table.contained),
      expanded,
      mobileSingleExpanded: width > 767 || expanded === 1,
      canvases,
      populatedChartsRender: canvases.length > 0 && canvases.every((canvas) => canvas.clientWidth > 0 && canvas.clientHeight > 0 && canvas.has2dContext)
    };
  });
}

async function diagnoseOverflow() {
  return page.evaluate(() => {
    const docWidth = document.documentElement.clientWidth;
    const hasOverflow = document.documentElement.scrollWidth > docWidth;

    if (!hasOverflow) {
      return { hasOverflow: false };
    }

    // Find the widest elements whose right edge extends past the viewport.
    const offenders = Array.from(document.querySelectorAll('body *'))
      .map((el) => {
        const r = el.getBoundingClientRect();
        return { el, right: r.right, width: r.width };
      })
      .filter((entry) => entry.right > docWidth + 1)
      .sort((a, b) => b.right - a.right)
      .slice(0, 3);

    function describe(el) {
      const style = window.getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        selector: el.tagName.toLowerCase() + (el.className ? `.${String(el.className).trim().replace(/\s+/g, '.')}` : ''),
        rect: { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right },
        display: style.display,
        flexWrap: style.flexWrap,
        minWidth: style.minWidth,
        width: style.width,
        overflowX: style.overflowX
      };
    }

    const results = offenders.map(({ el }) => {
      const chain = [];
      let node = el;
      let depth = 0;
      while (node && node !== document.body && depth < 8) {
        chain.push(describe(node));
        node = node.parentElement;
        depth += 1;
      }
      return chain;
    });

    return { hasOverflow: true, docWidth, offendersCount: offenders.length, chains: results };
  });
}

try {
  if (interact === 'device-modal') interaction = await runDeviceModal();
  else if (interact === 'alert-rule-modal') interaction = await runAlertRuleModal();
  else if (interact === 'toast') interaction = await runToastInjection();
  else if (interact === 'measure-buttons') interaction = { buttons: await measureButtons() };
  else if (interact === 'diagnose-overflow') interaction = await diagnoseOverflow();
  else if (interact === 'add-monitors') interaction = await addMonitors(2);
  else if (interact === 'dashboard-lifecycle') interaction = await runDashboardLifecycle();
  else if (interact === 'auth-transition') interaction = await runAuthTransition();
  else if (interact === 'responsive-layout') interaction = await inspectResponsiveLayout();
} catch (err) {
  interaction = { error: String(err) };
}

const screenshotPath = path.join(resultsDir, `${name}.png`);
if (!navError) {
  await page.screenshot({ path: screenshotPath, fullPage: true });
}

const summary = {
  name,
  route,
  role,
  mode,
  interact,
  viewport: { width, height },
  finalUrl,
  heading,
  overflow,
  interaction,
  consoleErrors,
  pageErrors,
  failedRequests,
  navError,
  screenshot: navError ? null : screenshotPath
};

fs.writeFileSync(path.join(resultsDir, `${name}.json`), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary));

// An explicitly requested interaction is part of the audit contract. Preserve the JSON artifact
// for diagnosis, but make its failed assertion or caught exception fail the matrix subprocess.
if (hasInteractionFailure(interaction)) process.exitCode = 1;

await browser.close();
