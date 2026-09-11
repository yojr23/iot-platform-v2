import { createApp, nextTick } from 'vue';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

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

async function mountBoard() {
  const { default: SensorMonitorBoard } = await import('./SensorMonitorBoard.vue');
  const el = document.createElement('div');
  const app = createApp(SensorMonitorBoard, { devices });
  app.use(createPinia());
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
