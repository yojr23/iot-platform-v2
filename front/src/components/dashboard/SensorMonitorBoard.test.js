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
  updateDashboardPreferences: vi.fn(() => Promise.resolve({}))
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

vi.mock('@/components/dashboard/MonitorCard.vue', () => ({
  default: {
    props: ['monitor'],
    template: '<button data-testid="monitor-card-device-change" @click="$emit(\'deviceChange\', monitor, \'2\')">change</button>'
  }
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

  it('refetches history when realtime is re-enabled so the missed window is repaired', async () => {
    const { el, unmount } = await mountBoard();
    fetchWindow.mockClear();

    const toggle = el.querySelector('#dashboardRealtimeToggle');
    toggle.checked = false;
    toggle.dispatchEvent(new Event('change'));
    await nextTick();
    toggle.checked = true;
    toggle.dispatchEvent(new Event('change'));
    await flush();

    expect(fetchWindow).toHaveBeenCalledWith('10', expect.objectContaining({ authorizationScope: 'public' }));
    unmount();
  });

  it('uses the extracted MonitorCard selection event to load the first sensor of the selected device', async () => {
    const { el, unmount } = await mountBoard();
    fetchWindow.mockClear();

    el.querySelector('[data-testid="monitor-card-device-change"]').click();
    await flush();

    expect(fetchWindow).toHaveBeenCalledWith('20', expect.objectContaining({ authorizationScope: 'public' }));
    unmount();
  });
});
