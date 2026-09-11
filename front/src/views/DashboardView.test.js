import { createApp, nextTick } from 'vue';
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
const getDevices = vi.fn(() => Promise.resolve({
  data: { data: [{ id: 9, name: 'Authenticated device', status: true, is_active: true }] }
}));
const getActiveAlerts = vi.fn(() => Promise.resolve({ data: { alerts: [], count: 0 } }));

vi.mock('@/api/dashboard', () => ({
  getDashboardMetrics: (...args) => getDashboardMetrics(...args),
  getDashboardPreferences: vi.fn(),
  updateDashboardPreferences: vi.fn()
}));

vi.mock('@/api/graph', () => ({
  getGraphBootstrap: (...args) => getGraphBootstrap(...args)
}));

vi.mock('@/api/devices', () => ({
  getDevices: (...args) => getDevices(...args)
}));

vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => getActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn()
}));

vi.mock('@/components/dashboard/SensorMonitorBoard.vue', () => ({
  default: { template: '<section data-testid="sensor-monitor-board" />' }
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
    // Protected widgets are authenticated-only and must be absent from the guest surface.
    expect(el.querySelector('[data-testid="device-status-list"]')).toBeFalsy();
    expect(el.querySelector('[data-testid="recent-readings-table"]')).toBeFalsy();

    unmount();
  });

  it('uses the shared graph catalog without fetching unrelated global metrics in private mode', async () => {
    const { el, unmount } = await mountDashboardView({ authenticated: true });

    expect(getDashboardMetrics).not.toHaveBeenCalled();
    expect(getDevices).toHaveBeenCalledOnce();
    expect(getGraphBootstrap).toHaveBeenCalledOnce();
    expect(el.querySelector('[data-testid="sensor-monitor-board"]')).toBeTruthy();

    unmount();
  });
});
