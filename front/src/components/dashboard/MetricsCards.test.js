import { createApp, nextTick } from 'vue';
import { describe, expect, it } from 'vitest';

async function renderMetricsCards(props) {
  const { default: MetricsCards } = await import('./MetricsCards.vue');
  const el = document.createElement('div');
  const app = createApp(MetricsCards, props);
  app.mount(el);
  await nextTick();
  return {
    el,
    unmount: () => app.unmount()
  };
}

describe('MetricsCards guest alert containment', () => {
  it('omits alert-count tiles by default', async () => {
    const { el, unmount } = await renderMetricsCards({
      summary: {
        total_devices: 3,
        active_devices: 2,
        total_sensors: 5,
        active_alerts: 7,
        unresolved_alerts: 4
      }
    });

    expect(el.textContent).toContain('Dispositivos');
    expect(el.textContent).toContain('Sensores');
    expect(el.textContent).not.toContain('Alertas activas');
    expect(el.textContent).not.toContain('No resueltas');

    unmount();
  });
});
