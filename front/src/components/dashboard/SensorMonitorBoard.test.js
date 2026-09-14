import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@/assets/styles/lab-blue.css';
import { alert as fixtureAlert } from '../../../.audit-e2e/fixtures.mjs';

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  onBeforeRouteLeave: vi.fn(),
  RouterLink: { template: '<a><slot /></a>' }
}));

// PLAN.md Stage 6 — "Delete in the same cutover": pollTimer / startPolling / stopPolling /
// refreshVisibleMonitors / refreshMonitor / the pollInterval prop are gone; this is the
// no-sensor-polling assertion the task calls for at the component level (the store/composable
// level cancellation and cutover behavior is covered by graphSeriesQuery.test.js and
// useSensorRealtime.test.js).

vi.mock('@/api/dashboard', () => ({
  getDashboardPreferences: vi.fn(() => Promise.resolve({ data: { layout: null } })),
  updateDashboardPreferences: vi.fn(() => Promise.resolve({})),
  getDashboardMetrics: vi.fn(() => Promise.resolve({ data: { total_devices: 2, active_devices: 1, active_alerts: 0 } }))
}));

const fetchWindow = vi.fn(() => Promise.resolve({ points: [], stats: { min: null, max: null, mean: null, count: 0 } }));
const resultForQuery = vi.fn(() => ({ points: [], stats: null, truncated: false, loading: false, error: '' }));
vi.mock('@/stores/graphSeriesQuery', () => ({
  useGraphSeriesQueryStore: () => ({ fetchWindow, resultForQuery })
}));

const subscribeSensor = vi.fn();
const unsubscribeSensor = vi.fn();
vi.mock('@/realtime/useSensorRealtime', () => ({
  useSensorRealtime: () => ({
    isRealtimeEnabled: { value: false },
    isConnected: { value: false },
    error: { value: '' },
    subscribeSensor,
    unsubscribeSensor
  }),
  RECOVERY_WINDOW_MS: 5 * 60 * 1000
}));

const devices = [
  { id: 1, name: 'Device A', sensors: [{ id: 10, name: 'Sensor A', unit: 'C' }] },
  { id: 2, name: 'Device B', sensors: [{ id: 20, name: 'Sensor B', unit: 'C' }] }
];

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

async function mountBoard(pinia = createPinia()) {
  const { default: SensorMonitorBoard } = await import('./SensorMonitorBoard.vue');
  const el = document.createElement('div');
  const app = createApp(SensorMonitorBoard, { devices });
  app.use(pinia);
  // The board is mounted standalone (outside router-view); treat navigation links as
  // inert stubs while useRouter/onBeforeRouteLeave are mocked above.
  const RouterLinkStub = { template: '<a><slot /></a>' };
  app.component('RouterLink', RouterLinkStub);
  app.component('router-link', RouterLinkStub);
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  await flush();
  await nextTick();
  return {
    el,
    unmount: () => {
      app.unmount();
      const index = mountedApps.indexOf(app);
      if (index >= 0) {
        mountedApps.splice(index, 1);
      }
    }
  };
}

describe('SensorMonitorBoard polling removal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    window.localStorage.clear();
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
    vi.useRealTimers();
    window.localStorage.clear();
  });

  it('never starts a polling interval and wires the sensor through the query layer + realtime composable instead', async () => {
    const { unmount } = await mountBoard();

    expect(vi.getTimerCount()).toBe(0);
    // monitor.sensor_id is stored as a normalized string ('10'), not the numeric device catalog id.
    expect(fetchWindow).toHaveBeenCalledWith('10', expect.objectContaining({ authorizationScope: 'public' }));
    expect(subscribeSensor).toHaveBeenCalled();

    unmount();
  });

  it('unsubscribes every live sensor handle on unmount instead of leaving a timer/subscription running', async () => {
    const { unmount } = await mountBoard();

    unmount();

    expect(unsubscribeSensor).toHaveBeenCalled();
    expect(vi.getTimerCount()).toBe(0);
  });

  it('requests an exact bounded window when the operator changes range', async () => {
    const { el, unmount } = await mountBoard();
    // Cards are mini previews by default; the range controls only render in the big view.
    el.querySelector('.lab-spark-grid .lab-spark-title .lab-text-button').click();
    await flush();
    fetchWindow.mockClear();
    [...el.querySelectorAll('.lab-ranges button')].find(b=>b.textContent==='1h').click();
    await flush();
    const [id, options] = fetchWindow.mock.calls.at(-1);
    expect(id).toBe('10');
    expect(options.to-options.from).toBe(3600000);
    unmount();
  });

  it('resets the selected sensor when its device changes', async () => {
    const { el, unmount } = await mountBoard();
    // Open the first card's big view so its device/sensor selectors render.
    el.querySelector('.lab-spark-grid .lab-spark-title .lab-text-button').click();
    await flush();
    fetchWindow.mockClear();

    const select = el.querySelector('.lab-chart-selectors select');
    select.value='2';
    select.dispatchEvent(new Event('change'));
    await flush();

    expect(fetchWindow).toHaveBeenCalledWith('20', expect.objectContaining({ authorizationScope: 'public' }));
    unmount();
  });
});

describe('SensorMonitorBoard multi-chart invariants', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    window.localStorage.clear();
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: 1440 });
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
    vi.useRealTimers();
    window.localStorage.clear();
  });

  it('expands multiple widgets in parallel on desktop when selected', async () => {
    const { el, unmount } = await mountBoard();
    // Board auto-expands first widget via watch. Second widget starts mini.
    const miniBodies = el.querySelectorAll('.lab-spark-grid .lab-spark-body');
    expect(miniBodies.length).toBe(1);
    // Select the second widget via the list sidebar — should also expand it.
    const listButtons = el.querySelectorAll('.lab-list-select');
    expect(listButtons.length).toBe(2);
    listButtons[1].click();
    await flush();
    // Both widgets should now be expanded (desktop allows parallel expansion).
    const expandedCards = el.querySelectorAll('.lab-main-chart');
    expect(expandedCards.length).toBe(2);
    // No mini widgets remain.
    expect(el.querySelectorAll('.lab-spark-grid .lab-spark-body').length).toBe(0);
    unmount();
  });

  it('forces single expanded chart on mobile when a second is selected', async () => {
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: 375 });
    const { el, unmount } = await mountBoard();
    await flush();
    // First widget is auto-expanded. Select the second via the list.
    const listButtons = el.querySelectorAll('.lab-list-select');
    listButtons[1].click();
    await flush();
    // Mobile: only one expanded card at a time.
    const expandedCards = el.querySelectorAll('.lab-main-chart');
    expect(expandedCards.length).toBe(1);
    unmount();
  });

  it('disables the "Ver mini" button when only one widget is expanded', async () => {
    const { el, unmount } = await mountBoard();
    // First widget auto-expanded, second is mini. Expand second to have 2 expanded.
    const listButtons = el.querySelectorAll('.lab-list-select');
    listButtons[1].click();
    await flush();
    // Now collapse one of them — "Ver mini" should disable since only 1 remains.
    const verMiniButtons = el.querySelectorAll('.lab-text-button[aria-pressed="true"]');
    expect(verMiniButtons.length).toBe(2);
    verMiniButtons[1].click();
    await flush();
    // Only 1 expanded now, "Ver mini" should be disabled.
    const remaining = el.querySelectorAll('.lab-text-button[aria-pressed="true"]');
    expect(remaining.length).toBe(1);
    expect(remaining[0].disabled).toBe(true);
    unmount();
  });

  it('selectSync expands a widget that was only selected in the list', async () => {
    const { el, unmount } = await mountBoard();
    // Click the second list item — selectSync should expand it too.
    const listButtons = el.querySelectorAll('.lab-list-select');
    listButtons[1].click();
    await flush();
    // The second widget should now appear as an expanded card.
    const expandedCards = el.querySelectorAll('.lab-main-chart');
    expect(expandedCards.length).toBe(2);
    // And it should be selected (has .selected class).
    expect(expandedCards[1].classList.contains('selected')).toBe(true);
    unmount();
  });

  it('updates wide flag on resize so the details panel opens/closes correctly', async () => {
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: 1440 });
    const { el, unmount } = await mountBoard();
    await flush();
    // At 1440px, the details <details> should have open attribute (wide = true).
    const details = el.querySelector('.lab-details');
    expect(details).toBeTruthy();
    expect(details.open).toBe(true);
    // Simulate resize to 768px — wide should become false.
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: 768 });
    window.dispatchEvent(new Event('resize'));
    await flush();
    expect(details.open).toBe(false);
    // Back to 1440px — wide should become true again.
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: 1440 });
    window.dispatchEvent(new Event('resize'));
    await flush();
    expect(details.open).toBe(true);
    unmount();
  });

  it('keeps monitoring controls but hides persistent dashboard management from a standard user', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const pinia = createPinia();
    setActivePinia(pinia);
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, is_admin: false };

    const { default: SensorMonitorBoard } = await import('./SensorMonitorBoard.vue');
    const el = document.createElement('div');
    const app = createApp(SensorMonitorBoard, { devices });
    app.use(pinia);
    const RouterLinkStub = { template: '<a><slot /></a>' };
    app.component('RouterLink', RouterLinkStub);
    app.component('router-link', RouterLinkStub);
    app.mount(el);
    mountedApps.push(app);
    await nextTick();
    await flush();
    await nextTick();

    // The approved role policy permits monitoring, not dashboard personalization.
    const allTextButtons = el.querySelectorAll('.lab-text-button');
    const editButton = Array.from(allTextButtons).find(btn => btn.textContent.trim() === 'Editar');
    expect(editButton).toBeFalsy();

    // The server is administrator-only for persistence, so no save affordance is rendered.
    const saveIndicator = el.querySelector('.lab-save-state');
    expect(saveIndicator).toBeFalsy();

    // The dialog stays mounted but closed; assert the visible toolbar/list entry points only.
    expect(el.querySelector('.lab-actions .lab-button')).toBeFalsy();
    expect(el.querySelector('.lab-add-row')).toBeFalsy();
    expect(el.querySelector('.lab-empty .lab-button.primary')).toBeFalsy();
    expect(el.querySelectorAll('.lab-ranges button').length).toBeGreaterThan(0);

    app.unmount();
    mountedApps.splice(mountedApps.indexOf(app), 1);
  });

  it('provides compact recent-alert fields while retaining the larger-screen table', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const { useAlertsStore } = await import('@/stores/alerts');
    const pinia = createPinia();
    setActivePinia(pinia);
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, is_admin: false };
    const alertsStore = useAlertsStore();
    alertsStore.activeAlerts = [{
      id: 101,
      created_at: '2026-09-14T12:34:00.000Z',
      message: 'Temperatura fuera de rango',
      device: { name: 'Device A' },
      alert_rule: { message: 'Temperatura fuera de rango', severity: 'info' }
    }];

    const { el, unmount } = await mountBoard(pinia);

    const compactCard = el.querySelector('.lab-alert-compact-card');
    expect(compactCard).toBeTruthy();
    expect(compactCard.textContent).toContain('12:34');
    expect(compactCard.textContent).toContain('Temperatura fuera de rango');
    expect(compactCard.textContent).toContain('Device A');
    expect(compactCard.querySelector('.lab-severity.info')).toBeTruthy();
    expect(compactCard.textContent).toContain('Info');
    // The board also owns a reading-history table. Scope this assertion to the alert table,
    // otherwise querySelector selects that earlier, unrelated table.
    const table = el.querySelector('.lab-events .lab-table-wrap');
    expect(table).toBeTruthy();
    expect(table.querySelector('.lab-severity.info')).toBeTruthy();
    expect(table.textContent).toContain('Info');
    unmount();
  });

  it('renders device and severity from the normal API-shaped alert fixture', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const { useAlertsStore } = await import('@/stores/alerts');
    const pinia = createPinia();
    setActivePinia(pinia);
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, is_admin: false };
    const alertsStore = useAlertsStore();
    alertsStore.activeAlerts = [fixtureAlert(1, 'normal')];

    const { el, unmount } = await mountBoard(pinia);

    const compactCard = el.querySelector('.lab-alert-compact-card');
    expect(compactCard).toBeTruthy();
    expect(compactCard.textContent).toContain('Device 1');
    expect(compactCard.querySelector('.lab-severity.info')).toBeTruthy();
    unmount();
  });
});
