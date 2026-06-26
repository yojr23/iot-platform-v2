<template>
  <div class="row g-3">
    <div v-for="metric in metrics" :key="metric.label" class="col-12 col-md-6 col-xl-3">
      <div class="metric-tile p-3 h-100" :class="`metric-tile--${metric.variant}`">
        <p class="small mb-2">{{ metric.label }}</p>
        <div class="d-flex align-items-end justify-content-between gap-3">
          <p class="display-6 fw-semibold mb-0">{{ metric.value }}</p>
          <span v-if="metric.hint" class="badge rounded-pill">{{ metric.hint }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

import { formatNumber } from '@/utils/formatters';

const props = defineProps({
  summary: {
    type: Object,
    default: () => ({})
  }
});

const metrics = computed(() => [
  {
    label: 'Dispositivos',
    value: formatNumber(props.summary.total_devices),
    hint: `${formatNumber(props.summary.active_devices)} activos`,
    variant: 'blue'
  },
  {
    label: 'Sensores',
    value: formatNumber(props.summary.total_sensors),
    variant: 'cyan'
  },
  {
    label: 'Alertas activas',
    value: formatNumber(props.summary.active_alerts),
    variant: 'danger'
  },
  {
    label: 'No resueltas',
    value: formatNumber(props.summary.unresolved_alerts),
    variant: 'slate'
  }
]);
</script>
