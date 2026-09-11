<template>
  <div class="sensor-reading-chart">
    <LoadingSpinner v-if="loading" label="Cargando grafica..." />

    <BaseAlert v-else-if="error" variant="danger" :message="error" />

    <p v-else-if="!hasData" class="text-muted small py-4 mb-0">
      No hay datos suficientes para graficar.
    </p>

    <div v-else>
      <div
        ref="chartContainer"
        class="sensor-chart"
        role="img"
        tabindex="0"
        :aria-label="accessibleSummary"
      >
        <Line :data="chartData" :options="chartOptions" />
      </div>

      <ul class="visually-hidden">
        <li v-for="(label, index) in labels" :key="label + index">
          {{ label }}: {{ formatNumber(series[index]) }} {{ unit }}
        </li>
      </ul>

      <p v-if="partial" class="small text-warning" role="status">Datos parciales. Las estadísticas describen las muestras devueltas.</p>

      <dl class="sensor-chart-stats d-flex flex-wrap gap-3 gap-md-4 mt-3 mb-0 small text-muted-strong">
        <div>
          <dt class="text-muted small mb-0">Minimo</dt>
          <dd class="mb-0 fw-semibold">{{ formatNumber(stats.min) }} {{ unit }}</dd>
        </div>
        <div>
          <dt class="text-muted small mb-0">Maximo</dt>
          <dd class="mb-0 fw-semibold">{{ formatNumber(stats.max) }} {{ unit }}</dd>
        </div>
        <div>
          <dt class="text-muted small mb-0">Promedio</dt>
          <dd class="mb-0 fw-semibold">{{ formatNumber(stats.mean) }} {{ unit }}</dd>
        </div>
        <div>
          <dt class="text-muted small mb-0">Muestras</dt>
          <dd class="mb-0 fw-semibold">{{ stats.count }}</dd>
        </div>
        <div v-if="lastObservedAt">
          <dt class="text-muted small mb-0">Ultimo dato</dt>
          <dd class="mb-0 fw-semibold">{{ formatDate(lastObservedAt) }}</dd>
        </div>
      </dl>
    </div>
  </div>
</template>

<script setup>
import {
  CategoryScale,
  Filler,
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip
} from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';

import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { resolveChartTokens, resolveZoneTokens } from '@/utils/chartTheme';
import { formatDate, formatNumber } from '@/utils/formatters';
import { zoneBackgroundPlugin } from './zoneBackgroundPlugin';

ChartJS.register(Filler, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, zoneBackgroundPlugin);

// Presentation-only chart owner. Honest V1 scope: last-observed timestamp,
// no-data/loading/error states, and min/max/mean/count for the returned
// set. Never call HTTP or Echo here, staleness labels, cadence/completeness/
// quality, or per-sensor precision rounding — those are unowned future
// contracts. docs/implementation/graph-semantic-zones-plan.md lifts the
// previous "never render threshold bands" rule: `zones` below is a
// server-owned, already-precedence-resolved view-model (graphZonesProjection.js
// / RuleToGraphZones), not an invented threshold — this component only maps
// it to pixels via the Y scale (zoneBackgroundPlugin.js), it never evaluates
// AlertRules itself.
const props = defineProps({
  labels: {
    type: Array,
    default: () => []
  },
  series: {
    type: Array,
    default: () => []
  },
  unit: {
    type: String,
    default: ''
  },
  stats: {
    type: Object,
    default: null
  },
  lastObservedAt: {
    type: String,
    default: ''
  },
  // When true, the returned points/stats describe only a truncated sample of the window, not the
  // full window — surfaced with a visible notice so partial stats are never read as full-window.
  partial: {
    type: Boolean,
    default: false
  },
  // Already-normalized `graphZonesProjection.buildZonesViewModel()` output. Optional — a chart
  // with no zones data (e.g. a sensor without bands) simply paints nothing extra.
  zones: {
    type: Object,
    default: () => ({ regions: [], boundaries: [], domainValues: [] })
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: ''
  }
});

const hasData = computed(() => props.series.length > 0 && Boolean(props.stats));

const chartContainer = ref(null);
let resizeObserver = null;

const tokens = resolveChartTokens();
const zoneTokens = resolveZoneTokens();

// GRAPH-009/010: the Y-axis domain must include both the observed values AND any configured
// boundary outside them (e.g. a danger threshold far above all current readings must still be
// visible), so a plain `beginAtZero:false` auto-range over just `series` is not enough.
const yDomain = computed(() => {
  const observed = props.series.filter((value) => Number.isFinite(value));
  const values = [...observed, ...props.zones.domainValues];
  if (!values.length) return { min: undefined, max: undefined };
  const min = Math.min(...values), max = Math.max(...values);
  // Leave room beyond configured boundaries so the outer warning/critical
  // bands remain visible even when every reading is inside the normal range.
  const padding = props.zones.domainValues.length ? (max - min || Math.abs(max) || 1) * 0.1 : 0;
  return { min: min - padding, max: max + padding };
});

const chartData = computed(() => ({
  labels: props.labels,
  datasets: [
    {
      label: props.unit ? `Valor (${props.unit})` : 'Valor',
      data: props.series,
      borderColor: tokens.line,
      backgroundColor: props.zones.regions.length ? 'transparent' : tokens.fill,
      tension: 0,
      borderWidth: 2,
      pointRadius: (ctx) => ctx.dataIndex === props.series.length - 1 ? 4 : 0,
      pointHoverRadius: 4,
      pointBackgroundColor: tokens.line,
      spanGaps: false,
      fill: props.zones.regions.length === 0
    }
  ]
}));

const chartOptions = computed(() => ({
  animation: false,
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        title(items) {
          if (!items.length) return '';
          const label = items[0].label || '';
          const match = label.match(/^(.+?)\s+UTC$/);
          return match ? match[1] : label;
        },
        label(item) {
          const value = formatNumber(item.parsed.y);
          const unit = props.unit ? ` ${props.unit}` : '';
          return `${value}${unit}`;
        },
        afterLabel(item) {
          const label = item.label || '';
          // Extract date and time from the full label ("11 sep 2026, 10:30:00 UTC")
          const parts = label.replace(' UTC', '').split(', ');
          if (parts.length === 2) {
            return `Zona: UTC`;
          }
          return '';
        }
      }
    },
    zoneBackground: {
      regions: props.zones.regions,
      boundaries: props.zones.boundaries,
      tokens: zoneTokens
    }
  },
  scales: {
    x: { ticks: { color: tokens.muted, font: { family: 'Inter', size: 11 }, maxTicksLimit: 6, maxRotation: 0, callback(value) { const label = this.getLabelForValue(value); const clock = label.match(/\b(\d{1,2}:\d{2})(?::\d{2})?/); return clock ? clock[1] : label; } }, grid: { display: false } },
    y: {
      beginAtZero: false,
      suggestedMin: yDomain.value.min,
      suggestedMax: yDomain.value.max,
      ticks: { color: tokens.muted, font: { family: 'Inter', size: 11 }, maxTicksLimit: 6 },
      grid: { color: '#eef2f7' }, border: { display: false }
    }
  }
}));

onMounted(() => {
  // ResizeObserver is unavailable in older browsers and our non-browser test environment.
  // Chart.js still handles window resizes there, so retain a working chart rather than failing
  // the entire component mount.
  if (!chartContainer.value || typeof ResizeObserver === 'undefined') return;
  resizeObserver = new ResizeObserver(() => {
    chartContainer.value?.dispatchEvent(new Event('resize'));
  });
  resizeObserver.observe(chartContainer.value);
});

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
});

const accessibleSummary = computed(() => {
  if (!hasData.value) {
    return 'Grafica de lecturas del sensor sin datos.';
  }

  const { min, max, mean, count } = props.stats;
  const unitSuffix = props.unit ? ` ${props.unit}` : '';
  const lastObserved = props.lastObservedAt ? ` Ultimo dato: ${formatDate(props.lastObservedAt)}.` : '';

  return `Grafica de tendencia con ${count} muestras. `
    + `Minimo ${formatNumber(min)}${unitSuffix}. `
    + `Maximo ${formatNumber(max)}${unitSuffix}. `
    + `Promedio ${formatNumber(mean)}${unitSuffix}.${lastObserved}`;
});
</script>
