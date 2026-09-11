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
