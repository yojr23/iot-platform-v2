<template>
  <section class="content-panel p-3">
    <h2 class="h5 mb-3">Ultimas lecturas</h2>

    <div v-if="readings.length === 0" class="text-muted small py-3">
      No hay lecturas recientes.
    </div>

    <div v-else class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Sensor</th>
            <th>Dispositivo</th>
            <th>Valor</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="reading in readings" :key="reading.id || `${reading.sensor?.id}-${reading.reading_time}`">
            <td>{{ reading.sensor?.name || '-' }}</td>
            <td>{{ reading.device?.name || '-' }}</td>
            <td>{{ formatNumber(reading.value) }} {{ reading.sensor?.unit || '' }}</td>
            <td>{{ formatDate(reading.reading_time || reading.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { formatDate, formatNumber } from '@/utils/formatters';

defineProps({
  readings: {
    type: Array,
    default: () => []
  }
});
</script>
