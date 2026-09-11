import { createApp, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getMetrics = vi.fn();
vi.mock('@/api/metrics', () => ({
  getMetrics: (...args) => getMetrics(...args),
}));

vi.mock('vue-chartjs', () => ({
  Doughnut: { template: '<div class="chart-stub" />' },
  Bar: { template: '<div class="chart-stub" />' },
}));

const apps = [];
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountMetricsView() {
  const { default: MetricsView } = await import('./MetricsView.vue');
  const host = document.createElement('div');
  const app = createApp(MetricsView);
  app.mount(host);
  apps.push(app);
  await flush();
  await nextTick();
  return host;
}

afterEach(() => apps.splice(0).forEach((app) => app.unmount()));
beforeEach(() => getMetrics.mockResolvedValue({ data: {
  total_devices: 12, online_devices: 10, offline_devices: 2,
  total_sensors: 24, readings_today: 384, active_alerts: 1,
  total_alert_rules: 6, enabled_rules: 5, total_labs: 3, uptime_percent: 99.7,
} }));

describe('MetricsView SINOA surface', () => {
  it('presents the monitoring snapshot in the shared resource shell with icon-led refresh and KPIs', async () => {
    const host = await mountMetricsView();

    expect(host.querySelector('.lab-resource-page')).not.toBeNull();
    expect(host.querySelector('.lab-resource-toolbar')).not.toBeNull();
    expect(host.querySelector('.lab-resource-actions .lab-action svg')).not.toBeNull();
    expect(host.querySelectorAll('.metric-kpi-icon svg')).toHaveLength(4);
    expect(host.textContent).toContain('Ver detalle numérico');
  });

  it('shows API-performance KPIs when the current endpoint returns its snapshot envelope', async () => {
    getMetrics.mockResolvedValueOnce({ data: {
      generated_at: '2026-09-11T16:00:00Z',
      snapshot: { window_minutes: 10, requests_total: 42, errors_total: 2, error_rate_percent: 4.76, avg_latency_ms: 78.5, throughput_rpm_avg: 4.2, series: [] },
    } });

    const host = await mountMetricsView();

    expect(host.textContent).toContain('Solicitudes en 10 min');
    expect(host.textContent).toContain('78.5 ms');
    expect(host.textContent).not.toContain('Total dispositivos');
  });
});
