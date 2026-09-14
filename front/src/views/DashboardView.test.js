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

async function mountDashboardView({ authenticated = false } = {}) {
  const { default: DashboardView } = await import('./DashboardView.vue');
  const el = document.createElement('div');
  const app = createApp(DashboardView);
  app.use(createPinia());
  if (authenticated) {
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };
  }
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  await flush();
  await nextTick();
  return {
    el,
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
    const { el, unmount } = await mountDashboardView({ authenticated: true });

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
});
