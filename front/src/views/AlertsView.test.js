import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const alert = {
  id: 1,
  resolved: false,
  created_at: '2026-09-11T10:00:00Z',
  alert_rule: { name: 'Temperatura alta', severity: 'warning' },
  sensor: { name: 'Temperatura', unit: '°C' },
  device: { name: 'Reactor 1' }
};

const getAlerts = vi.fn(() => Promise.resolve({ data: { data: [alert] } }));
const getActiveAlerts = vi.fn(() => Promise.resolve({ data: { alerts: [alert] } }));
const getUnresolvedAlerts = vi.fn(() => Promise.resolve({ data: { data: [alert] } }));
const resolveAlert = vi.fn(() => Promise.resolve({ data: { data: { ...alert, resolved: true } } }));

vi.mock('@/api/alerts', () => ({
  getAlerts: (...args) => getAlerts(...args),
  getActiveAlerts: (...args) => getActiveAlerts(...args),
  getUnresolvedAlerts: (...args) => getUnresolvedAlerts(...args),
  resolveAlert: (...args) => resolveAlert(...args),
  resolveAllAlerts: vi.fn(() => Promise.resolve({ data: { message: 'ok' } }))
}));

const mountedApps = [];
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountAlertsView() {
  const { default: AlertsView } = await import('./AlertsView.vue');
  const host = document.createElement('div');
  const app = createApp(AlertsView);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.component('RouterLink', { template: '<a><slot /></a>', props: ['to'] });
  app.mount(host);
  mountedApps.push(app);
  await flush();
  await nextTick();
  return { app, host };
}

describe('AlertsView resolution feedback', () => {
  beforeEach(() => vi.clearAllMocks());
  afterEach(() => mountedApps.splice(0).forEach((app) => app.unmount()));

  it('keeps the success confirmation visible after resolving an alert and refreshing the list', async () => {
    const { host } = await mountAlertsView();

    [...host.querySelectorAll('button')].find((button) => button.textContent.trim() === 'Resolver')
      .dispatchEvent(new Event('click', { bubbles: true }));
    await flush();
    await flush();
    await nextTick();

    expect(resolveAlert).toHaveBeenCalledWith(1);
    expect(host.textContent).toContain('Alerta resuelta correctamente.');
  });
});

describe('AlertsView syncResolvedFromStore regression', () => {
  beforeEach(() => vi.clearAllMocks());
  afterEach(() => mountedApps.splice(0).forEach((app) => app.unmount()));

  it('preserves both the resolved target and historical rows in the all filter', async () => {
    const resolvedHistory = {
      id: 99,
      resolved: true,
      created_at: '2026-09-10T08:00:00Z',
      alert_rule: { name: 'Humedad baja', severity: 'info' },
      sensor: { name: 'Humedad', unit: '%' },
      device: { name: 'Reactor 2' }
    };
    const activeAlert = {
      id: 20,
      resolved: false,
      created_at: '2026-09-11T10:00:00Z',
      alert_rule: { name: 'Temperatura alta', severity: 'warning' },
      sensor: { name: 'Temperatura', unit: '°C' },
      device: { name: 'Reactor 1' }
    };

    getAlerts.mockResolvedValue({ data: { data: [activeAlert, resolvedHistory] } });

    const { host } = await mountAlertsView();

    // Both alerts should be visible in the "all" filter
    expect(host.textContent).toContain('Temperatura alta');
    expect(host.textContent).toContain('Humedad baja');

    // Simulate WebSocket resolving alert #20 via the store
    const { useAlertsStore } = await import('@/stores/alerts');
    const store = useAlertsStore();

    // Seed store state as if the alert was active
    store.activeAlerts = [{ ...activeAlert }];
    store.unresolvedCount = 1;

    // Trigger the resolve — this removes #20 from activeAlerts and publishes latestResolvedId.
    store.markAlertResolved(20);
    await nextTick();
    await flush();

    // In the "all" filter, both the resolved target and unrelated history must survive.
    expect(host.textContent).toContain('Humedad baja');
    expect(host.textContent).toContain('Temperatura alta');
    const resolvedTargetRow = [...host.querySelectorAll('tbody tr')]
      .find((row) => row.textContent.includes('Temperatura alta'));
    expect(resolvedTargetRow.textContent).toContain('Resuelta');
  });

  it.each([
    ['Activas', getActiveAlerts],
    ['No resueltas', getUnresolvedAlerts]
  ])('removes only the resolved target from the %s filter', async (filterLabel, requestMock) => {
    const resolvedHistory = {
      id: 99,
      resolved: true,
      alert_rule: { name: 'Humedad baja', severity: 'info' },
      sensor: { name: 'Humedad', unit: '%' },
      device: { name: 'Reactor 2' }
    };
    const activeAlert = {
      id: 20,
      resolved: false,
      alert_rule: { name: 'Temperatura alta', severity: 'warning' },
      sensor: { name: 'Temperatura', unit: '°C' },
      device: { name: 'Reactor 1' }
    };
    requestMock.mockResolvedValue(requestMock === getUnresolvedAlerts
      ? { data: { data: [activeAlert, resolvedHistory] } }
      : { data: { alerts: [activeAlert, resolvedHistory] } });

    const { host } = await mountAlertsView();
    host.querySelectorAll('button').forEach((button) => {
      if (button.textContent.trim() === filterLabel) {
        button.dispatchEvent(new Event('click', { bubbles: true }));
      }
    });
    await flush();
    await nextTick();

    const { useAlertsStore } = await import('@/stores/alerts');
    const store = useAlertsStore();
    store.markAlertResolved(20);
    await nextTick();
    await flush();

    expect(host.textContent).not.toContain('Temperatura alta');
    expect(host.textContent).toContain('Humedad baja');
  });
});
