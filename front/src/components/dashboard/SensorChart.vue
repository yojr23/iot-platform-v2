<template>
  <section class="content-panel p-3 h-100">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">Lecturas recientes</h2>
        <p class="text-muted small mb-0">Grafica basada en datos disponibles de la API.</p>
      </div>
    </div>

    <div v-if="chartReadings.length === 0" class="text-muted small py-4">
      No hay datos suficientes para graficar.
    </div>

    <div v-else class="sensor-chart">
      <Line :data="chartData" :options="chartOptions" />
    </div>
  </section>
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
  }
});

const chartReadings = computed(() => [...props.readings].reverse());

const chartData = computed(() => ({
  labels: chartReadings.value.map((reading) => formatDate(reading.reading_time || reading.created_at)),
  datasets: [
    {
      label: 'Valor',
      data: chartReadings.value.map((reading) => Number(reading.value ?? 0)),
      borderColor: '#0d6efd',
      backgroundColor: 'rgba(13, 110, 253, 0.14)',
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
</script>
