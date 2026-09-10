<template>
  <div class="sensor-reading-chart">
    <LoadingSpinner v-if="loading" label="Cargando grafica..." />

    <BaseAlert v-else-if="error" variant="danger" :message="error" />

    <p v-else-if="!hasData" class="text-muted small py-4 mb-0">
      No hay datos suficientes para graficar.
    </p>

    <div v-else>
      <div
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

      <p v-if="partial" class="small text-warning" role="status">Partial data for this window. Statistics describe only the returned sample.</p>

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
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { resolveChartTokens } from '@/utils/chartTheme';
import { formatDate, formatNumber } from '@/utils/formatters';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend);

// Presentation-only chart owner. Honest V1 scope: last-observed timestamp,
// no-data/loading/error states, and min/max/mean/count for the returned
// set. Never call HTTP or Echo here, never render threshold bands,
// staleness labels, cadence/completeness/quality, or per-sensor precision
// rounding — those are unowned future contracts.
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

const tokens = resolveChartTokens();

const chartData = computed(() => ({
  labels: props.labels,
  datasets: [
    {
      label: props.unit ? `Valor (${props.unit})` : 'Valor',
      data: props.series,
      borderColor: tokens.line,
      backgroundColor: tokens.fill,
      tension: 0.3,
      fill: true
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: false
    }
  }
};

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
