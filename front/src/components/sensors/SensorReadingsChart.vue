<template>
  <div class="content-panel p-3 h-100">
    <h2 class="h5 mb-3">Tendencia</h2>

    <div v-if="chartReadings.length === 0" class="text-muted small py-4">
      No hay datos suficientes para graficar.
    </div>

    <div v-else class="sensor-chart">
      <Line :data="chartData" :options="chartOptions" />
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
  Title,
  Tooltip
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

import { formatDate } from '@/utils/formatters';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

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

const chartReadings = computed(() => [...props.readings].reverse());

const chartData = computed(() => ({
  labels: chartReadings.value.map((reading) => formatDate(reading.reading_time || reading.created_at)),
  datasets: [
    {
      label: props.unit ? `Valor (${props.unit})` : 'Valor',
      data: chartReadings.value.map((reading) => Number(reading.value ?? 0)),
      borderColor: '#198754',
      backgroundColor: 'rgba(25, 135, 84, 0.13)',
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
  }
};
</script>
