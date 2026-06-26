<template>
  <div class="content-panel p-3">
    <h2 class="h5 mb-3">Lecturas</h2>

    <div v-if="readings.length === 0" class="text-muted small py-3">
      No hay lecturas para mostrar.
    </div>

    <div v-else class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Valor</th>
            <th>Fecha lectura</th>
            <th>Creacion</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="reading in readings" :key="reading.id || reading.reading_time">
            <td>{{ formatNumber(reading.value) }} {{ unit }}</td>
            <td>{{ formatDate(reading.reading_time) }}</td>
            <td>{{ formatDate(reading.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { formatDate, formatNumber } from '@/utils/formatters';

defineProps({
  readings: {
    type: Array,
    default: () => []
  },
  unit: {
    type: String,
    default: ''
  }
});
</script>
