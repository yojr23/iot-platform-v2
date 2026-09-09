import { createApp, nextTick } from 'vue';
import { describe, expect, it } from 'vitest';

async function renderChart(props) {
  const { default: SensorChart } = await import('./SensorChart.vue');
  const el = document.createElement('div');
  const app = createApp(SensorChart, props);
  app.mount(el);
  await nextTick();
  return { el, unmount: () => app.unmount() };
}

describe('SensorChart (dashboard adapter)', () => {
  it('keeps the existing "Lecturas recientes" panel heading', async () => {
    const { el, unmount } = await renderChart({ readings: [] });

    expect(el.textContent).toContain('Lecturas recientes');

    unmount();
  });

  it('shows the no-data message when there are no readings', async () => {
    const { el, unmount } = await renderChart({ readings: [] });

    expect(el.textContent).toContain('No hay datos suficientes para graficar.');

    unmount();
  });

  it('delegates readings to the shared chart owner and renders honest V1 stats', async () => {
    const readings = [
      { value: 30, reading_time: '2026-01-01T10:02:00Z' },
      { value: 10, reading_time: '2026-01-01T10:00:00Z' }
    ];

    const { el, unmount } = await renderChart({ readings });

    expect(el.querySelector('[role="img"]')).toBeTruthy();
    expect(el.textContent).toContain('10');
    expect(el.textContent).toContain('30');

    unmount();
  });
});
