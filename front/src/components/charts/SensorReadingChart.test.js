import { createApp, nextTick } from 'vue';
import { describe, expect, it } from 'vitest';

async function renderChart(props) {
  const { default: SensorReadingChart } = await import('./SensorReadingChart.vue');
  const el = document.createElement('div');
  const app = createApp(SensorReadingChart, props);
  app.mount(el);
  await nextTick();
  return { el, unmount: () => app.unmount() };
}

const sampleViewModel = {
  labels: ['10:00', '10:01', '10:02'],
  series: [10, 20, 30],
  unit: 'C',
  stats: { min: 10, max: 30, mean: 20, count: 3 },
  lastObservedAt: '2026-01-01T10:02:00Z'
};

describe('SensorReadingChart', () => {
  it('shows a loading state and no chart while loading', async () => {
    const { el, unmount } = await renderChart({ ...sampleViewModel, loading: true });

    expect(el.textContent).toContain('Cargando');
    expect(el.querySelector('canvas')).toBeFalsy();

    unmount();
  });

  it('shows an error state and no chart when error is set', async () => {
    const { el, unmount } = await renderChart({ ...sampleViewModel, error: 'No se pudo cargar la grafica.' });

    expect(el.querySelector('[role="alert"]')).toBeTruthy();
    expect(el.textContent).toContain('No se pudo cargar la grafica.');
    expect(el.querySelector('canvas')).toBeFalsy();

    unmount();
  });

  it('shows a no-data message when there are no readings', async () => {
    const { el, unmount } = await renderChart({ labels: [], series: [], unit: 'C', stats: null, lastObservedAt: '' });

    expect(el.textContent).toContain('No hay datos suficientes para graficar.');
    expect(el.querySelector('canvas')).toBeFalsy();

    unmount();
  });

  it('renders the chart canvas and the honest V1 stats when data is present', async () => {
    const { el, unmount } = await renderChart(sampleViewModel);

    expect(el.querySelector('canvas')).toBeTruthy();
    expect(el.textContent).toContain('10');
    expect(el.textContent).toContain('30');
    expect(el.textContent).toContain('20');
    expect(el.textContent).toContain('3');

    unmount();
  });

  it('shows a partial-data notice only when partial is set, never labelling partial stats as full-window', async () => {
    const full = await renderChart(sampleViewModel);
    expect(full.el.textContent).not.toContain('Datos parciales');
    full.unmount();

    const partial = await renderChart({ ...sampleViewModel, partial: true });
    const notice = partial.el.querySelector('[role="status"]');
    expect(notice).toBeTruthy();
    expect(notice.textContent).toContain('Datos parciales');
    partial.unmount();
  });

  it('does not render threshold, staleness, cadence, or quality copy', async () => {
    const { el, unmount } = await renderChart(sampleViewModel);

    const text = el.textContent.toLowerCase();
    expect(text).not.toContain('umbral');
    expect(text).not.toContain('obsoleto');
    expect(text).not.toContain('cadencia');
    expect(text).not.toContain('completitud');
    expect(text).not.toContain('calidad');

    unmount();
  });

  it('exposes an accessible textual summary, not just a color-coded chart', async () => {
    const { el, unmount } = await renderChart(sampleViewModel);

    const figure = el.querySelector('[role="img"]');
    expect(figure).toBeTruthy();
    expect(figure.getAttribute('aria-label')).toBeTruthy();
    expect(figure.getAttribute('aria-label')).toContain('3');

    const hiddenValues = el.querySelector('.visually-hidden');
    expect(hiddenValues).toBeTruthy();
    expect(hiddenValues.textContent).toContain('10');
    expect(hiddenValues.textContent).toContain('30');

    unmount();
  });

  it('is keyboard-reachable (focusable) so the accessible summary is announced without a mouse', async () => {
    const { el, unmount } = await renderChart(sampleViewModel);

    const figure = el.querySelector('[role="img"]');
    expect(figure.getAttribute('tabindex')).toBe('0');

    unmount();
  });
});
