import { createApp, nextTick } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';

const apps = [];

async function mount(component, props) {
  const host = document.createElement('div');
  const app = createApp(component, props);
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.mount(host);
  apps.push(app);
  await nextTick();
  return host;
}

afterEach(() => apps.splice(0).forEach((app) => app.unmount()));

describe('mobile content-panel table labels', () => {
  it('labels every Alerts cell for the global mobile card layout', async () => {
    const { default: AlertList } = await import('./AlertList.vue');
    const host = await mount(AlertList, {
      alerts: [{
        id: 7,
        alert_rule: { name: 'Temperatura alta', severity: 'danger' },
        sensor: { name: 'T-1', unit: '°C' },
        device: { name: 'Gateway A' },
        sensor_reading: { value: 41 },
        resolved: false,
        created_at: '2026-09-15T10:00:00Z'
      }]
    });

    expect([...host.querySelectorAll('tbody td')].map((cell) => cell.dataset.label)).toEqual([
      'Alerta', 'Severidad', 'Valor', 'Estado', 'Fecha', 'Acciones'
    ]);
  });

  it('labels every Alert Rules cell for the global mobile card layout', async () => {
    const { default: AlertRuleList } = await import('@/components/alert-rules/AlertRuleList.vue');
    const host = await mount(AlertRuleList, {
      rules: [{
        id: 12,
        name: 'Temperatura máxima',
        sensor: { name: 'T-1' },
        sensor_type: { name: 'Temperatura', unit: '°C' },
        severity: 'warning',
        min_value: null,
        max_value: 35,
        message: 'Valor fuera de rango'
      }]
    });

    expect([...host.querySelectorAll('tbody td')].map((cell) => cell.dataset.label)).toEqual([
      'Nombre', 'Alcance', 'Severidad', 'Umbrales', 'Mensaje', 'Acciones'
    ]);
  });
});
