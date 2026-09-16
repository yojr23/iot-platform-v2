import { createApp, h, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getDashboardMetrics = vi.fn(() => Promise.resolve({
  data: {
    total_devices: 3,
    active_devices: 2,
    total_sensors: 5,
    active_alerts: 7,
    unresolved_alerts: 4,
    devices: []
  }
}));
const getGraphBootstrap = vi.fn(() => Promise.resolve({
  data: { version: 1, default_sensor_id: null, devices: [] }
}));
const getAuthenticatedGraphCatalog = vi.fn(() => Promise.resolve({
  data: {
    version: 1,
    default_sensor_id: 17,
    devices: [{
      id: 9,
      name: 'Authenticated device',
      sensors: [{ id: 17, name: 'Restricted sensor', unit: '°C', bands: [], boundaries: [] }]
    }]
  }
}));
const getActiveAlerts = vi.fn(() => Promise.resolve({ data: { alerts: [], count: 0 } }));
const boardDevices = vi.fn();

vi.mock('@/api/dashboard', () => ({
  getDashboardMetrics: (...args) => getDashboardMetrics(...args),
  getDashboardPreferences: vi.fn(),
  updateDashboardPreferences: vi.fn()
}));

vi.mock('@/api/graph', () => ({
  getGraphBootstrap: (...args) => getGraphBootstrap(...args),
  getAuthenticatedGraphCatalog: (...args) => getAuthenticatedGraphCatalog(...args)
}));

vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => getActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn()
}));

vi.mock('@/components/dashboard/SensorMonitorBoard.vue', () => ({
  default: {
    props: { devices: { type: Array, required: true } },
    setup(props) {
      boardDevices(props.devices);
      return () => h('section', {
        'data-testid': 'sensor-monitor-board',
        'data-device-ids': props.devices.map((device) => device.id).join(',')
      });
    }
  }
}));

vi.mock('@/components/dashboard/DeviceStatusList.vue', () => ({
  default: { template: '<section data-testid="device-status-list" />' }
}));

vi.mock('@/components/dashboard/RecentReadingsTable.vue', () => ({
  default: { template: '<section data-testid="recent-readings-table" />' }
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((promiseResolve, promiseReject) => {
    resolve = promiseResolve;
    reject = promiseReject;
  });
  return { promise, resolve, reject };
}

async function mountDashboardView({ authenticated = false, permissions = [] } = {}) {
  const { default: DashboardView } = await import('./DashboardView.vue');
  const { useAuthStore } = await import('@/stores/auth');
  const el = document.createElement('div');
  const app = createApp(DashboardView);
  const pinia = createPinia();
  app.use(pinia);
  const authStore = useAuthStore(pinia);
  if (authenticated) {
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User', role: { code: 'user' }, permissions };
  }
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  await flush();
  await nextTick();
  return {
    el,
    authStore,
    unmount: () => {
      app.unmount();
      const appIndex = mountedApps.indexOf(app);
      if (appIndex >= 0) {
        mountedApps.splice(appIndex, 1);
      }
    }
  };
}

describe('DashboardView guest alert containment', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    setActivePinia(createPinia());
    window.localStorage.clear();
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
    vi.useRealTimers();
    window.localStorage.clear();
  });

  it('does not render or initialize active alerts for a guest dashboard mount', async () => {
    const { el, unmount } = await mountDashboardView();

    expect(el.textContent).not.toContain('Alertas activas');
    expect(el.textContent).not.toContain('dispositivos y alertas activas');
    expect(el.textContent).not.toContain('Estado:');
    expect(getActiveAlerts).not.toHaveBeenCalled();
    expect(vi.getTimerCount()).toBe(0);

    unmount();
  });

  it('makes no authenticated dashboard-metrics call as a guest but still bootstraps the public graph', async () => {
    const { el, unmount } = await mountDashboardView();

    expect(getDashboardMetrics).not.toHaveBeenCalled();
    expect(getGraphBootstrap).toHaveBeenCalledTimes(1);
    expect(getAuthenticatedGraphCatalog).not.toHaveBeenCalled();
    // Protected widgets are authenticated-only and must be absent from the guest surface.
    expect(el.querySelector('[data-testid="device-status-list"]')).toBeFalsy();
    expect(el.querySelector('[data-testid="recent-readings-table"]')).toBeFalsy();

    unmount();
  });

  it('passes the authenticated graph catalog including a restricted sensor to the actual board prop', async () => {
    const { el, unmount } = await mountDashboardView({
      authenticated: true,
      permissions: ['sensor.view'],
    });

    expect(getDashboardMetrics).not.toHaveBeenCalled();
    expect(getGraphBootstrap).toHaveBeenCalledOnce();
    expect(getAuthenticatedGraphCatalog).toHaveBeenCalledOnce();
    expect(el.querySelector('[data-testid="sensor-monitor-board"]')).toBeTruthy();
    expect(el.querySelector('[data-testid="sensor-monitor-board"]')?.dataset.deviceIds).toBe('9');
    expect(boardDevices).toHaveBeenLastCalledWith(expect.arrayContaining([
      expect.objectContaining({
        id: 9,
        sensors: [expect.objectContaining({ id: 17, name: 'Restricted sensor' })]
      })
    ]));

    unmount();
  });

  it('aborts an in-flight authenticated catalog request on logout and ignores its stale result', async () => {
    const catalog = deferred();
    let catalogSignal;
    getAuthenticatedGraphCatalog.mockImplementationOnce(({ signal }) => {
      catalogSignal = signal;
      return catalog.promise;
    });

    const { el, authStore, unmount } = await mountDashboardView({
      authenticated: true,
      permissions: ['sensor.view'],
    });

    expect(getAuthenticatedGraphCatalog).toHaveBeenCalledOnce();
    expect(catalogSignal?.aborted).toBe(false);

    authStore.clearAuth();
    await nextTick();
    await flush();

    expect(catalogSignal?.aborted).toBe(true);

    catalog.resolve({
      data: {
        devices: [{ id: 99, name: 'Stale restricted device', sensors: [] }],
      },
    });
    await flush();
    await nextTick();

    expect(el.querySelector('[data-testid="sensor-monitor-board"]')?.dataset.deviceIds).not.toContain('99');

    unmount();
  });

  it('merges auth and public devices with auth taking precedence on overlap', async () => {
    getGraphBootstrap.mockResolvedValueOnce({
      data: {
        version: 1,
        default_sensor_id: null,
        devices: [
          { id: 9, name: 'Public copy of auth device', sensors: [{ id: 99, name: 'Public-only sensor', unit: 'pH' }] },
          { id: 5, name: 'Public-only device', sensors: [{ id: 50, name: 'Public sensor', unit: '°C' }] }
        ]
      }
    });
    getAuthenticatedGraphCatalog.mockResolvedValueOnce({
      data: {
        version: 1,
        default_sensor_id: 17,
        devices: [
          { id: 9, name: 'Auth device', sensors: [{ id: 17, name: 'Restricted sensor', unit: '°C' }] }
        ]
      }
    });

    const { el, unmount } = await mountDashboardView({ authenticated: true });

    const board = el.querySelector('[data-testid="sensor-monitor-board"]');
    expect(board).toBeTruthy();
    // Device 9 appears once (auth version wins), device 5 appears from public fallback
    const deviceIds = board.dataset.deviceIds.split(',').map(Number);
    expect(deviceIds).toContain(9);
    expect(deviceIds).toContain(5);
    expect(deviceIds.filter((id) => id === 9)).toHaveLength(1);
    // The auth version (name "Auth device") should be in the data, not "Public copy"
    expect(boardDevices).toHaveBeenLastCalledWith(
      expect.arrayContaining([
        expect.objectContaining({ id: 9, name: 'Auth device' }),
        expect.objectContaining({ id: 5, name: 'Public-only device' }),
      ])
    );

    unmount();
  });
});
