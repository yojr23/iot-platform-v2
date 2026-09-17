<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">{{ sensor?.name || `Sensor ${id}` }}</h1>
        <p class="lab-resource-description">Consulta las lecturas en tiempo real y explora el historial del sensor.</p>
      </div>
      <div class="lab-resource-actions">
        <span
          class="badge"
          :class="realtimeBadge.className"
          role="status"
          aria-live="polite"
          :data-realtime-mode="realtimeBadge.mode"
        >{{ realtimeBadge.label }}</span>
        <button
          v-if="canExportTelemetry"
          class="btn btn-outline-info"
          type="button"
          :disabled="exporting"
          @click="exportReadings"
        >Exportar lecturas</button>
        <RouterLink class="btn btn-outline-secondary" to="/sensors">Volver</RouterLink>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="sensorRealtime.error.value" variant="warning" :message="sensorRealtime.error.value" />
    <BaseAlert
      v-if="!loading && !canViewTelemetry"
      variant="warning"
      message="Telemetría no disponible para esta sesión. Puedes consultar los datos del sensor, pero no sus lecturas."
    />
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
            <dt>Unidad</dt>
            <dd>{{ sensor?.unit || sensor?.sensor_type?.unit || '-' }}</dd>
            <dt>Dispositivo</dt>
            <dd>{{ sensor?.device?.name || sensor?.device_name || '-' }}</dd>
            <dt>Descripcion</dt>
            <dd>{{ sensor?.description || '-' }}</dd>
            <dt>Estado</dt>
            <dd>
              <span class="badge" :class="sensor?.status ? 'text-bg-success' : 'text-bg-secondary'">
                {{ sensor?.status ? 'Activo' : 'Inactivo' }}
              </span>
            </dd>
          </dl>
        </div>
      </div>

      <div v-if="canViewTelemetry" class="col-12 col-xl-8">
        <SensorReadingsChart :readings="readings" :unit="sensor?.unit || sensor?.sensor_type?.unit || ''" />
      </div>

      <div v-if="canViewTelemetry" class="col-12">
        <div class="content-panel p-3 mb-3">
          <form class="row g-3 align-items-end" @submit.prevent="filterReadings">
            <div class="col-12 col-md-4">
              <BaseInput v-model="filters.from" label="Desde" name="readings_from" type="date" />
            </div>
            <div class="col-12 col-md-4">
              <BaseInput v-model="filters.to" label="Hasta" name="readings_to" type="date" />
            </div>
            <div class="col-12 col-md-4">
              <div class="lab-resource-actions">
                <BaseButton type="submit" variant="outline-primary" :loading="filtering">Filtrar</BaseButton>
                <button class="btn btn-outline-secondary" type="button" @click="resetFilter">Limpiar</button>
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
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import { exportSensorReadings, getSensor, getSensorLatestReadings, getSensorReadings } from '@/api/sensors';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import SensorReadingsChart from '@/components/sensors/SensorReadingsChart.vue';
import SensorReadingsTable from '@/components/sensors/SensorReadingsTable.vue';
import { useSensorRealtime } from '@/realtime/useSensorRealtime';
import { useSensorReadingsStore } from '@/stores/sensorReadings';
import { useAuthStore } from '@/stores/auth';
import { paginatedItems } from '@/utils/formatters';
import { createLogger } from '@/utils/logger';

const log = createLogger('SensorDetailView');

const authStore = useAuthStore();

const props = defineProps({
  id: {
    type: String,
    required: true
  }
});

const readingsStore = useSensorReadingsStore();
const canViewTelemetry = computed(() => authStore.can('sensor_reading.view'));
const canExportTelemetry = computed(() => authStore.can('sensor_reading.export'));
const sensor = ref(null);
const loading = ref(false);
const error = ref('');
const filtering = ref(false);
const exporting = ref(false);
const filters = reactive({
  from: '',
  to: ''
});

// Gate 6: the default (unfiltered) live view is a view over the SHARED live tail store — no second
// local live cache. The explicit historical filter keeps its OWN immutable result that live events
// never touch; exiting the filter falls straight back to the shared tail.
const filterActive = ref(false);
const filteredReadings = ref([]);
const readings = computed(() => (
  filterActive.value
    ? filteredReadings.value
    // Shared tail is chronological ascending; the chart/table want newest-first.
    : [...readingsStore.readingsFor(props.id)].reverse()
));

function readingFilterParams() {
  return {
    ...(filters.from ? { from: filters.from } : {}),
    ...(filters.to ? { to: filters.to } : {})
  };
}

// A->B race guard shared by loadTelemetry() and filterReadings(): both re-read props.id after
// an await, so a request started for sensor A that resolves after navigating to B (or after
// telemetry access is revoked) must not touch B's store/error/loading state. One counter + one
// AbortController for both functions — starting either one supersedes/aborts the other, and
// navigation/revoke invalidate whatever is in flight without starting a new request.
let telemetryAbort = null;
let telemetryGeneration = 0;

function invalidateTelemetry() {
  telemetryGeneration++;
  telemetryAbort?.abort();
  telemetryAbort = null;
}

async function filterReadings() {
  if (!canViewTelemetry.value) {
    return;
  }

  const generation = ++telemetryGeneration;
  const requestedId = props.id;
  telemetryAbort?.abort();
  telemetryAbort = new AbortController();
  const { signal } = telemetryAbort;
  const isStale = () => generation !== telemetryGeneration || requestedId !== props.id;

  filtering.value = true;
  error.value = '';

  try {
    log.debug('filterReadings for sensor', requestedId, readingFilterParams());
    const response = await getSensorReadings(requestedId, { ...readingFilterParams(), signal });

    if (isStale()) {
      return;
    }

    // Local immutable historical result for the filtered range — live events do not mutate it.
    filteredReadings.value = paginatedItems(response);
    filterActive.value = true;
    log.debug('filterReadings returned', filteredReadings.value.length, 'readings');
  } catch (requestError) {
    if (isStale()) {
      return;
    }
    if (requestError?.name !== 'CanceledError' && requestError?.code !== 'ERR_CANCELED') {
      log.warn('filterReadings failed:', requestError?.message);
      error.value = getApiErrorMessage(requestError, 'No se pudieron filtrar las lecturas.');
    }
  } finally {
    if (!isStale()) {
      filtering.value = false;
    }
  }
}

async function resetFilter() {
  filters.from = '';
  filters.to = '';
  filteredReadings.value = [];
  filterActive.value = false;
  log.debug('resetFilter: cleared filters, reloading telemetry');
  await loadTelemetry();
}

async function exportReadings() {
  if (!canExportTelemetry.value) {
    return;
  }

  exporting.value = true;
  error.value = '';

  try {
    log.info('exportReadings for sensor', props.id);
    const response = await exportSensorReadings(props.id, readingFilterParams());
    const payload = JSON.stringify(response.data, null, 2);
    const blob = new Blob([payload], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sensor_${props.id}_readings.json`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => window.URL.revokeObjectURL(url), 100);
    log.debug('exportReadings completed');
  } catch (requestError) {
    log.warn('exportReadings failed:', requestError?.message);
    error.value = getApiErrorMessage(requestError, 'No se pudieron exportar las lecturas.');
  } finally {
    exporting.value = false;
  }
}

// Live events feed the shared tail store (id-based dedup lives there), not a local array.
const sensorRealtime = useSensorRealtime(() => props.id, (reading) => {
  readingsStore.mergeReading(props.id, reading);
}, {
  canSubscribe: () => canViewTelemetry.value
});

// Same className mapping as DeviceRealtimeStatus.vue's four-mode badge, with sensor-specific
// Spanish labels.
const REALTIME_BADGE_BY_MODE = {
  live: { label: 'Tiempo real', className: 'text-bg-success' },
  recovering: { label: 'Actualizando…', className: 'text-bg-warning' },
  stale: { label: 'Datos posiblemente desactualizados', className: 'text-bg-warning' },
  disconnected: { label: 'Sin conexión en tiempo real', className: 'text-bg-secondary' }
};

const realtimeBadge = computed(() => {
  if (!canViewTelemetry.value) {
    return { mode: 'unavailable', label: 'Telemetría no disponible', className: 'text-bg-secondary' };
  }

  const mode = sensorRealtime.realtimeStatus.value.mode;
  return { mode, ...(REALTIME_BADGE_BY_MODE[mode] || REALTIME_BADGE_BY_MODE.disconnected) };
});

async function loadTelemetry() {
  if (!canViewTelemetry.value) {
    log.debug('loadTelemetry skipped: sensor_reading.view not granted');
    return;
  }

  const generation = ++telemetryGeneration;
  const requestedId = props.id;
  telemetryAbort?.abort();
  telemetryAbort = new AbortController();
  const { signal } = telemetryAbort;
  const isStale = () => generation !== telemetryGeneration || requestedId !== props.id;

  try {
    log.debug('loadTelemetry fetching latest readings for sensor', requestedId);
    const readingsResponse = await getSensorLatestReadings(requestedId, { limit: 20, signal });

    if (isStale() || !canViewTelemetry.value) {
      return;
    }

    readingsStore.mergeReadings(requestedId, unwrapData(readingsResponse) || []);
    log.debug('loadTelemetry merged', unwrapData(readingsResponse)?.length ?? 0, 'readings');
  } catch (requestError) {
    if (isStale()) {
      return;
    }
    if (requestError?.name !== 'CanceledError' && requestError?.code !== 'ERR_CANCELED') {
      log.warn('loadTelemetry failed:', requestError?.message);
      error.value = getApiErrorMessage(requestError, 'No se pudieron cargar las lecturas del sensor.');
    }
  }
}

function clearTelemetryProjection() {
  invalidateTelemetry();
  sensorRealtime.unsubscribeSensor();
  readingsStore.clearSensor(props.id);
  filteredReadings.value = [];
  filterActive.value = false;
}

// OPTION A (auto re-acquire): a re-grant after a prior revoke resubscribes AND reloads
// telemetry, same as useDeviceStatusRealtime.js's 'auth' resync auto-resubscribing — it does not
// require a manual page reload to see live data again.
watch(canViewTelemetry, async (allowed, previouslyAllowed) => {
  if (allowed) {
    log.debug('sensor_reading.view granted, subscribing sensor');
    sensorRealtime.subscribeSensor();
    if (previouslyAllowed === false && sensor.value) {
      await loadTelemetry();
    }
    return;
  }

  if (previouslyAllowed) {
    log.debug('sensor_reading.view revoked, clearing telemetry projection');
    clearTelemetryProjection();
  }
}, { immediate: true });

let metadataAbort = null;

async function load() {
  loading.value = true;
  error.value = '';
  filterActive.value = false;
  metadataAbort?.abort();
  metadataAbort = new AbortController();

  try {
    log.info('load: fetching metadata for sensor', props.id);
    const sensorResponse = await getSensor(props.id, { signal: metadataAbort.signal });

    sensor.value = unwrapData(sensorResponse);
    log.debug('load: metadata loaded, name=', sensor.value?.name);

    // Merge (not replace): the subscribe-before-snapshot order means a live event may already have
    // landed in the shared tail — merging preserves it and dedups the overlap by id.
    await loadTelemetry();
  } catch (requestError) {
    if (requestError?.name !== 'CanceledError' && requestError?.code !== 'ERR_CANCELED') {
      log.warn('load failed:', requestError?.message);
      error.value = getApiErrorMessage(requestError, 'No se pudo cargar el sensor.');
    }
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await load();
});

// Vue Router reuses this component when only :id changes (A -> B does not unmount/remount),
// so without this watcher navigating between sensors would leave A's metadata, subscription,
// and shared-tail projection showing under B's route. Old-first ordering (abort + unsubscribe
// + clear A) then load+subscribe B avoids a stale A response landing in B's projection.
watch(() => props.id, async (newId, oldId) => {
  if (newId === oldId) {
    return;
  }

  log.info('sensor id changed', oldId, '->', newId);
  metadataAbort?.abort();
  invalidateTelemetry();
  sensorRealtime.unsubscribeSensor();
  readingsStore.clearSensor(oldId);
  sensor.value = null;
  filteredReadings.value = [];
  filterActive.value = false;
  error.value = '';

  // Subscribe to B's live channel BEFORE the REST snapshot (Bug 2): the composable already reads
  // props.id via () => props.id, which Vue has updated to newId by the time this watcher runs, so
  // subscribeSensor() here targets B. Any reading emitted between now and the snapshot response
  // lands in the shared tail store and dedups against the snapshot on merge — nothing is lost.
  if (canViewTelemetry.value) {
    sensorRealtime.subscribeSensor();
  }

  await load();
});

onBeforeUnmount(() => {
  metadataAbort?.abort();
  invalidateTelemetry();
  sensorRealtime.unsubscribeSensor();
});
</script>
