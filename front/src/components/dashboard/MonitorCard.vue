<template>
  <article class="monitor-card h-100">
    <div class="monitor-card__header">
      <div>
        <p class="text-muted small mb-1">{{ monitor.id === 'main' ? 'Principal' : `Grafica ${index}` }}</p>
        <h3 class="h5 mb-0">{{ selectedSensorName }}</h3>
      </div>

      <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de grafica">
        <button
          v-if="monitor.id !== 'main'"
          class="btn btn-outline-secondary"
          type="button"
          :disabled="index <= 1"
          @click="$emit('move', monitor.id, -1)"
        >
          Subir
        </button>
        <button
          v-if="monitor.id !== 'main'"
          class="btn btn-outline-secondary"
          type="button"
          :disabled="index >= totalMonitors - 1"
          @click="$emit('move', monitor.id, 1)"
        >
          Bajar
        </button>
        <button
          v-if="monitor.id !== 'main'"
          class="btn btn-outline-danger"
          type="button"
          @click="$emit('remove', monitor.id)"
        >
          Eliminar
        </button>
      </div>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-12 col-md-6">
        <label class="form-label small text-muted" :for="`device-${monitor.id}`">Dispositivo</label>
        <select
          :id="`device-${monitor.id}`"
          :value="monitor.device_id"
          class="form-select"
          @change="$emit('deviceChange', monitor, $event.target.value)"
        >
          <option value="">Seleccione un dispositivo</option>
          <option v-for="device in devices" :key="device.id" :value="device.id">
            {{ device.name }}
          </option>
        </select>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label small text-muted" :for="`sensor-${monitor.id}`">Sensor</label>
        <select
          :id="`sensor-${monitor.id}`"
          :value="monitor.sensor_id"
          class="form-select"
          :disabled="sensorOptions.length === 0"
          @change="$emit('sensorChange', monitor, $event.target.value)"
        >
          <option value="">Seleccione un sensor</option>
          <option v-for="sensor in sensorOptions" :key="sensor.id" :value="sensor.id">
            {{ sensor.name }}{{ sensor.unit ? ` (${sensor.unit})` : '' }}
          </option>
        </select>
      </div>
    </div>

    <div class="monitor-card__meta">
      <span>{{ pointCount }} puntos</span>
      <span v-if="latestPoint">
        Ultimo: {{ formatNumber(latestPoint.value) }} {{ selectedSensor?.unit || '' }}
      </span>
    </div>

    <div class="monitor-chart">
      <SensorReadingChart
        v-bind="chartViewModel"
        :loading="loading"
        :error="error"
      />
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';

import SensorReadingChart from '@/components/charts/SensorReadingChart.vue';
import { formatNumber } from '@/utils/formatters';

const props = defineProps({
  monitor: { type: Object, required: true },
  index: { type: Number, required: true },
  totalMonitors: { type: Number, required: true },
  devices: { type: Array, default: () => [] },
  sensorOptions: { type: Array, default: () => [] },
  selectedSensor: { type: Object, default: null },
  selectedSensorName: { type: String, default: 'Sensor sin seleccionar' },
  pointCount: { type: Number, default: 0 },
  latestPoint: { type: Object, default: null },
  chartViewModel: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

defineEmits(['move', 'remove', 'deviceChange', 'sensorChange']);
</script>
