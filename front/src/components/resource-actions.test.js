import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, describe, expect, it } from 'vitest';

const mountedApps = [];

async function mountComponent(component, template, state = {}) {
  const host = document.createElement('div');
  const app = createApp({
    components: { ComponentUnderTest: component },
    data: () => state,
    template
  });
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.component('RouterLink', { template: '<a><slot /></a>', props: ['to'] });
  app.mount(host);
  mountedApps.push(app);
  await nextTick();
  return host;
}

afterEach(() => mountedApps.splice(0).forEach((app) => app.unmount()));

describe('resource action affordances', () => {
  it('gives device row actions an icon and a stable action-control class', async () => {
    const { default: DeviceList } = await import('./devices/DeviceList.vue');
    const host = await mountComponent(
      DeviceList,
      '<ComponentUnderTest :devices="devices" />',
      { devices: [{ id: 1, name: 'Reactor 1', status: true, is_active: true, sensors: [] }] }
    );

    const actions = [...host.querySelectorAll('[aria-label="Acciones de dispositivo"] .lab-action')];
    // A non-admin retains the public detail action; admin-only actions are covered by
    // the same component template and remain role-gated.
    expect(actions).toHaveLength(1);
    expect(actions.every((action) => action.querySelector('svg'))).toBe(true);
  });

  it('gives alert actions an icon and preserves their visible labels', async () => {
    const { default: AlertItem } = await import('./alerts/AlertItem.vue');
    const host = await mountComponent(
      AlertItem,
      '<table><tbody><ComponentUnderTest :alert="alert" /></tbody></table>',
      { alert: { id: 1, resolved: false, created_at: '2026-09-11T10:00:00Z', alert_rule: { severity: 'warning', name: 'Temperatura alta' }, sensor: { name: 'S-1', unit: '°C' }, device: { name: 'Reactor 1' } } }
    );

    const actions = [...host.querySelectorAll('[aria-label="Acciones de alerta"] .lab-action')];
    expect(actions).toHaveLength(2);
    expect(actions.map((action) => action.textContent.trim())).toEqual(['Ver', 'Resolver']);
    expect(actions.every((action) => action.querySelector('svg'))).toBe(true);
  });
});
