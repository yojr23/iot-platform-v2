<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="h3 mb-1">{{ sensor?.name || `Sensor ${id}` }}</h1>
        <p class="text-muted mb-0">Detalle, ultimas lecturas, filtro historico y exportacion.</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge" :class="sensorRealtime.isConnected.value ? 'text-bg-success' : 'text-bg-secondary'">
          {{ sensorRealtime.isConnected.value ? 'Tiempo real' : 'API' }}
        </span>
        <RouterLink class="btn btn-outline-secondary" to="/sensors">Volver</RouterLink>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="sensorRealtime.error.value" variant="warning" :message="sensorRealtime.error.value" />
    <LoadingSpinner v-if="loading" label="Cargando sensor..." />

    <div v-if="!loading" class="row g-3">
      <div class="col-12 col-xl-4">
        <div class="content-panel p-3">
          <h2 class="h5">Datos del sensor</h2>
          <dl class="mb-0">
            <dt>ID</dt>
            <dd>{{ sensor?.id || id }}</dd>
            <dt>Tipo</dt>
            <dd>{{ sensor?.type?.name || sensor?.sensor_type?.name || '-' }}</dd>
            <dt>Dispositivo</dt>
            <dd>{{ sensor?.device?.name || sensor?.device_name || '-' }}</dd>
            <dt>Estado</dt>
            <dd>
              <span class="badge" :class="sensor?.status ? 'text-bg-success' : 'text-bg-secondary'">
                {{ sensor?.status ? 'Activo' : 'Inactivo' }}
              </span>
            </dd>
          </dl>
        </div>
      </div>

      <div class="col-12 col-xl-8">
        <SensorReadingsChart :readings="readings" :unit="sensor?.unit || sensor?.sensor_type?.unit || ''" />
      </div>

      <div class="col-12">
        <div class="content-panel p-3 mb-3">
          <form class="row g-3 align-items-end" @submit.prevent="filterReadings">
            <div class="col-12 col-md-4">
              <BaseInput v-model="filters.from" label="Desde" name="readings_from" type="date" />
            </div>
            <div class="col-12 col-md-4">
              <BaseInput v-model="filters.to" label="Hasta" name="readings_to" type="date" />
            </div>
            <div class="col-12 col-md-4">
              <div class="d-flex gap-2">
                <BaseButton type="submit" variant="outline-primary" :loading="filtering">Filtrar</BaseButton>
                <button class="btn btn-outline-secondary" type="button" @click="resetFilter">Limpiar</button>
                <button class="btn btn-outline-info" type="button" :disabled="exporting" @click="exportReadings">Exportar</button>
              </div>
            </div>
          </form>
        </div>
        <SensorReadingsTable :readings="readings" :unit="sensor?.unit || sensor?.sensor_type?.unit || ''" />
      </div>
    </div>
  </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';

import { exportSensorReadings, getSensor, getSensorLatestReadings, getSensorReadings } from '@/api/sensors';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import SensorReadingsChart from '@/components/sensors/SensorReadingsChart.vue';
import SensorReadingsTable from '@/components/sensors/SensorReadingsTable.vue';
import { useSensorRealtime } from '@/realtime/useSensorRealtime';
import { paginatedItems } from '@/utils/formatters';

const props = defineProps({
  id: {
    type: String,
    required: true
  }
});

const sensor = ref(null);
const readings = ref([]);
const loading = ref(false);
const error = ref('');
const filtering = ref(false);
const exporting = ref(false);
const filters = reactive({
  from: '',
  to: ''
});

function addRealtimeReading(reading) {
  if (!reading?.reading_id && !reading?.id) {
    return;
  }

  const readingId = Number(reading.reading_id ?? reading.id);
  const exists = readings.value.some((item) => Number(item.id ?? item.reading_id) === readingId);

  if (exists) {
    readings.value = readings.value.map((item) => (
      Number(item.id ?? item.reading_id) === readingId ? { ...item, ...reading } : item
    ));
    return;
  }

  readings.value = [reading, ...readings.value].slice(0, 50);
}

function readingFilterParams() {
  return {
    ...(filters.from ? { from: filters.from } : {}),
    ...(filters.to ? { to: filters.to } : {})
  };
}

async function filterReadings() {
  filtering.value = true;
  error.value = '';

  try {
    const response = await getSensorReadings(props.id, readingFilterParams());
    readings.value = paginatedItems(response);
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron filtrar las lecturas.');
  } finally {
    filtering.value = false;
  }
}

async function resetFilter() {
  filters.from = '';
  filters.to = '';
  await load();
}

async function exportReadings() {
  exporting.value = true;
  error.value = '';

  try {
    const response = await exportSensorReadings(props.id, readingFilterParams());
    const payload = JSON.stringify(response.data, null, 2);
    const blob = new Blob([payload], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sensor_${props.id}_readings.json`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron exportar las lecturas.');
  } finally {
    exporting.value = false;
  }
}

const sensorRealtime = useSensorRealtime(() => props.id, addRealtimeReading);

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const [sensorResponse, readingsResponse] = await Promise.all([
      getSensor(props.id),
      getSensorLatestReadings(props.id, { limit: 20 })
    ]);

    sensor.value = unwrapData(sensorResponse);
    readings.value = unwrapData(readingsResponse) || [];
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo cargar el sensor.');
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await load();
  sensorRealtime.subscribeSensor();
});

onBeforeUnmount(() => {
  sensorRealtime.unsubscribeSensor();
});
</script>
