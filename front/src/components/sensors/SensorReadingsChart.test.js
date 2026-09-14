import { createApp, nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';

vi.mock('vue-chartjs', async () => {
  const { LineChartStub } = await import('@/test/chartStub');
  return { Line: LineChartStub };
});

async function renderChart(props) {
  const { default: SensorReadingsChart } = await import('./SensorReadingsChart.vue');
  const el = document.createElement('div');
  const app = createApp(SensorReadingsChart, props);
  app.mount(el);
  await nextTick();
  return { el, unmount: () => app.unmount() };
}

describe('SensorReadingsChart (sensor detail adapter)', () => {
  it('keeps the existing "Tendencia" panel heading', async () => {
    const { el, unmount } = await renderChart({ readings: [], unit: 'C' });

    expect(el.textContent).toContain('Tendencia');

    unmount();
  });

  it('shows the no-data message when there are no readings', async () => {
    const { el, unmount } = await renderChart({ readings: [], unit: 'C' });

    expect(el.textContent).toContain('No hay datos suficientes para graficar.');

    unmount();
  });

  it('delegates readings/unit to the shared chart owner and renders honest V1 stats', async () => {
    const readings = [
      { value: 30, reading_time: '2026-01-01T10:02:00Z' },
      { value: 10, reading_time: '2026-01-01T10:00:00Z' }
    ];

    const { el, unmount } = await renderChart({ readings, unit: 'C' });

    const chart = el.querySelector('[data-chart-stub="line"]');
    expect(chart).toBeTruthy();
    const wrapper = el.querySelector('.sensor-chart[role="img"]');
    expect(wrapper).toBeTruthy();
    expect(wrapper.getAttribute('aria-label')).toMatch(/grafica.*muestras/i);
    expect(JSON.parse(chart.getAttribute('data-chart-series'))).toEqual([10, 30]);
    // The display hour follows the operator's browser time zone; the source-minute order does
    // not. Keep the adapter contract portable across the UTC CI runner and local browsers.
    expect(JSON.parse(chart.getAttribute('data-chart-labels'))).toEqual([
      expect.stringMatching(/:00/),
      expect.stringMatching(/:02/)
    ]);
    expect(chart.getAttribute('data-chart-unit')).toContain('C');
    expect(el.textContent).toContain('10');
    expect(el.textContent).toContain('30');
    expect(el.textContent).toContain('2');

    unmount();
  });
});
