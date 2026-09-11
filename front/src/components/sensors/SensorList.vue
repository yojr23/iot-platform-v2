<template>
  <div class="content-panel">
    <div v-if="sensors.length === 0" class="text-center text-muted py-5">
      No hay sensores para mostrar.
    </div>

    <div v-else class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Dispositivo</th>
            <th>Laboratorio</th>
            <th>Estado</th>
            <th>Última lectura</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sensor in sensors" :key="sensor.id">
            <td class="fw-semibold">{{ sensor.name || `Sensor ${sensor.id}` }}</td>
            <td>{{ sensor.sensor_type?.name || '-' }}</td>
            <td>{{ sensor.device?.name || '-' }}</td>
            <td>{{ sensor.device?.lab?.name || '-' }}</td>
            <td>
              <span class="badge" :class="sensor.status ? 'text-bg-success' : 'text-bg-secondary'">
                {{ statusLabel(sensor.status) }}
              </span>
            </td>
            <td>
              <span v-if="latestReadingFor(sensor)">
                {{ formatNumber(latestReadingFor(sensor).value) }} {{ sensor.unit || '' }}
                <span class="text-muted small d-block">{{ formatDate(latestReadingFor(sensor).reading_time) }}</span>
              </span>
              <span v-else class="text-muted">-</span>
            </td>
            <td class="text-end">
              <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de sensor">
                <RouterLink class="btn btn-outline-primary lab-action" :to="`/sensors/${sensor.id}`"><I name="eye" />Ver</RouterLink>
                <button class="btn btn-outline-info lab-action" type="button" @click="$emit('export', sensor)"><I name="download" />Exportar</button>
                <button
                  v-if="authStore.user?.is_admin"
                  class="btn btn-outline-warning lab-action"
                  type="button"
                  @click="$emit('edit', sensor)"
                >
                  <I name="edit" />Editar
                </button>
                <button
                  v-if="authStore.user?.is_admin"
                  class="btn btn-outline-danger lab-action"
                  type="button"
                  @click="$emit('delete', sensor)"
                >
                  <I name="trash" />Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, watch } from 'vue';

import { useSensorRealtime } from '@/realtime/useSensorRealtime';
import { useSensorReadingsStore } from '@/stores/sensorReadings';
import { useAuthStore } from '@/stores/auth';
import { formatDate, formatNumber, statusLabel } from '@/utils/formatters';
import I from '@/components/dashboard/lab/LabIcon.vue';

const authStore = useAuthStore();
const readingsStore = useSensorReadingsStore();

const props = defineProps({
  sensors: {
    type: Array,
    default: () => []
  }
});

defineEmits(['edit', 'delete', 'export']);

// Prefer the live store (realtime supersedes), fall back to the reading eager-loaded in the
// initial /sensors response so the column shows a value on first paint, not "-".
function latestReadingFor(sensor) {
  return readingsStore.latestFor(sensor.id) ?? sensor.latest_reading;
}

// S1: live-patch each rendered row's last reading through the EXISTING shared realtime path
// (one useSensorRealtime instance per visible sensor, each backed by the ref-counted channel
// registry on top of the single Echo connection — no polling, no second connection).
const subscriptions = new Map(); // sensorId -> unsubscribeSensor()

function subscribeToSensor(sensorId) {
  if (subscriptions.has(sensorId)) {
    return;
  }

  const realtime = useSensorRealtime(() => sensorId, (reading) => {
    readingsStore.mergeReading(sensorId, reading);
  });
  realtime.subscribeSensor();
  subscriptions.set(sensorId, realtime.unsubscribeSensor);
}

function unsubscribeFromSensor(sensorId) {
  subscriptions.get(sensorId)?.();
  subscriptions.delete(sensorId);
}

watch(
  () => props.sensors.map((sensor) => sensor.id),
  (ids) => {
    const idSet = new Set(ids);

    [...subscriptions.keys()]
      .filter((id) => !idSet.has(id))
      .forEach(unsubscribeFromSensor);

    ids.forEach(subscribeToSensor);
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  [...subscriptions.keys()].forEach(unsubscribeFromSensor);
});
</script>
