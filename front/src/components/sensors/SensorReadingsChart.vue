<template>
  <div class="content-panel p-3 h-100">
    <h2 class="h5 mb-3">Tendencia</h2>

    <SensorReadingChart v-bind="viewModel" />
  </div>
</template>

<script setup>
import { computed } from 'vue';

import SensorReadingChart from '@/components/charts/SensorReadingChart.vue';
import { buildSensorChartViewModel } from '@/components/charts/sensorChartViewModel';

const props = defineProps({
  readings: {
    type: Array,
    default: () => []
  },
  unit: {
    type: String,
    default: ''
  }
});

const viewModel = computed(() => buildSensorChartViewModel(props.readings, { unit: props.unit }));
</script>
