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
const resolveAlert = vi.fn(() => Promise.resolve({ data: { data: { ...alert, resolved: true } } }));

vi.mock('@/api/alerts', () => ({
  getAlerts: (...args) => getAlerts(...args),
  getActiveAlerts: vi.fn(() => Promise.resolve({ data: { alerts: [alert] } })),
  getUnresolvedAlerts: vi.fn(() => Promise.resolve({ data: { data: [alert] } })),
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

  it('preserves resolved history when a WebSocket AlertResolved arrives for a different alert', async () => {
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

    // Trigger the resolve — this fires markAlertResolved which removes #20 from activeAlerts
    // and sets latestAlert, then syncResolvedFromStore should only remove #20 from local list
    store.markAlertResolved(20);
    await nextTick();
    await flush();

    // Resolved history (#99) must survive — only the just-resolved alert (#20) is removed
    expect(host.textContent).toContain('Humedad baja');
    expect(host.textContent).not.toContain('Temperatura alta');
  });
});
