<template>
  <section class="content-panel p-3 h-100">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">Lecturas recientes</h2>
        <p class="text-muted small mb-0">Grafica basada en datos disponibles de la API.</p>
      </div>
    </div>

    <SensorReadingChart v-bind="viewModel" />
  </section>
</template>

<script setup>
import { computed } from 'vue';

import SensorReadingChart from '@/components/charts/SensorReadingChart.vue';
import { buildSensorChartViewModel } from '@/components/charts/sensorChartViewModel';

const props = defineProps({
  readings: {
    type: Array,
    default: () => []
  }
});

const viewModel = computed(() => buildSensorChartViewModel(props.readings, { unit: '' }));
</script>
