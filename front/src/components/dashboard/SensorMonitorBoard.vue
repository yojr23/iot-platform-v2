<template>
  <section class="monitor-board">
    <div class="content-panel monitor-toolbar p-3 p-lg-4 mb-3">
      <div>
        <p class="section-kicker mb-1">Graficas configurables</p>
        <h2 class="h4 mb-1">Monitor de sensores</h2>
        <p class="text-muted mb-0">
          Agrega, ordena y consulta lecturas por dispositivo sin iniciar sesion.
        </p>
      </div>

      <div class="monitor-toolbar__actions">
        <div class="form-check form-switch mb-0">
          <input
            id="dashboardRealtimeToggle"
            v-model="realtimeEnabled"
            class="form-check-input"
            type="checkbox"
          />
          <label class="form-check-label" for="dashboardRealtimeToggle">Tiempo real</label>
        </div>

        <button class="btn btn-primary" type="button" :disabled="devices.length === 0" @click="addMonitor">
          Agregar grafica
        </button>
      </div>
    </div>

    <BaseAlert
      v-if="devices.length === 0"
      variant="info"
      message="No hay dispositivos disponibles para graficar."
    />

    <div v-else class="row g-3">
      <div
        v-for="(monitor, index) in visibleMonitors"
        :key="monitor.id"
        class="col-12"
        :class="monitor.id === 'main' ? 'col-xxl-8' : 'col-xxl-4 col-lg-6'"
      >
        <article class="monitor-card h-100">
          <div class="monitor-card__header">
            <div>
              <p class="text-muted small mb-1">{{ monitor.id === 'main' ? 'Principal' : `Grafica ${index}` }}</p>
              <h3 class="h5 mb-0">{{ selectedSensorName(monitor) }}</h3>
            </div>

            <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de grafica">
              <button
                v-if="monitor.id !== 'main'"
                class="btn btn-outline-secondary"
                type="button"
                :disabled="index <= 1"
                @click="moveMonitor(monitor.id, -1)"
              >
                Subir
              </button>
              <button
                v-if="monitor.id !== 'main'"
                class="btn btn-outline-secondary"
                type="button"
                :disabled="index >= visibleMonitors.length - 1"
                @click="moveMonitor(monitor.id, 1)"
              >
                Bajar
              </button>
              <button
                v-if="monitor.id !== 'main'"
                class="btn btn-outline-danger"
                type="button"
                @click="removeMonitor(monitor.id)"
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
                v-model="monitor.device_id"
                class="form-select"
                @change="handleDeviceChange(monitor)"
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
                v-model="monitor.sensor_id"
                class="form-select"
                :disabled="availableSensors(monitor).length === 0"
                @change="handleSensorChange(monitor)"
              >
                <option value="">Seleccione un sensor</option>
                <option v-for="sensor in availableSensors(monitor)" :key="sensor.id" :value="sensor.id">
                  {{ sensor.name }}{{ sensor.unit ? ` (${sensor.unit})` : '' }}
                </option>
              </select>
            </div>
          </div>

          <div class="monitor-card__meta">
            <!-- Stage 6 graph-only: the public graph bootstrap intentionally does NOT expose sensor
                 operational status, so no Activo/Sin-estado badge is rendered (it would always read
                 "Sin estado" and imply a device-health claim the graph contract doesn't own). -->
            <span>{{ pointCount(monitor) }} puntos</span>
            <span v-if="latestPoint(monitor)">
              Ultimo: {{ formatNumber(latestPoint(monitor).value) }} {{ selectedSensor(monitor)?.unit || '' }}
            </span>
          </div>

          <div class="monitor-chart">
            <SensorReadingChart
              v-bind="chartViewModel(monitor)"
              :loading="loadingByMonitor[monitor.id]"
              :error="readErrorByMonitor[monitor.id]"
            />
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import { getDashboardPreferences, updateDashboardPreferences } from '@/api/dashboard';
import BaseAlert from '@/components/base/BaseAlert.vue';
import SensorReadingChart from '@/components/charts/SensorReadingChart.vue';
import { buildSensorChartViewModel } from '@/components/charts/sensorChartViewModel';
import { composeGraphSeries } from '@/components/charts/graphSeriesProjection';
import { RECOVERY_WINDOW_MS, useSensorRealtime } from '@/realtime/useSensorRealtime';
import { useGraphSeriesQueryStore } from '@/stores/graphSeriesQuery';
import { useSensorReadingsStore } from '@/stores/sensorReadings';
import { useAuthStore } from '@/stores/auth';
import { formatNumber } from '@/utils/formatters';

// PLAN.md Stage 6.2 — cutover from component-local polling to the shared live sensor
// projection + historical graph query layer. This board is the guest-capable "Lab Blue" graph
// surface (PLAN.md 6.0/6.2): it always queries the public graph bootstrap/series contract,
// never an authenticated-only endpoint, regardless of whether the viewer happens to be logged
// in — guest and authenticated visitors see the same graph workflow.
//
// Existing code reused: add/remove/move/select monitor behavior and the dashboard-preferences /
// localStorage persistence below are unchanged from the pre-cutover version. The readings
// merge/history/MAX_POINTS behavior itself moved verbatim to stores/sensorReadings.js (kept,
// not rewritten) per the G0D ownership rule.
// Deleted in this cutover (PLAN.md Stage 6, "Delete in the same cutover"): `pollTimer`,
// `startPolling`/`stopPolling`, `refreshVisibleMonitors`, `refreshMonitor`, and the
// `pollInterval` prop / browser interval configuration. No polling fallback remains.

const props = defineProps({
  devices: {
    type: Array,
    default: () => []
  }
});

const LOCAL_STORAGE_KEY = 'iot-platform-v2.dashboard_layout';
const authStore = useAuthStore();
const sensorReadingsStore = useSensorReadingsStore();
const graphSeriesQueryStore = useGraphSeriesQueryStore();

const mainMonitor = reactive({
  id: 'main',
  device_id: '',
  sensor_id: ''
});
const monitors = ref([]);
const loadingByMonitor = reactive({});
const readErrorByMonitor = reactive({});
// monitor.id -> { from, to }: the exact window a monitor last asked for, so the descriptor used to
// READ the cached historical result matches the one used to WRITE it (buildGraphQueryKey keys on
// from/to). Mirrors loadingByMonitor/readErrorByMonitor; kept out of the persisted layout on purpose.
const queryByMonitor = reactive({});
const realtimeEnabled = ref(true);
const restoring = ref(false);

let persistTimer = null;
// monitor.id -> { realtime: ReturnType<useSensorRealtime>, sensorId }. Not reactive state —
// mirrors channelRegistry.js's own module-scope bookkeeping convention; these are subscription
// handles, not data to render.
const realtimeHandles = new Map();

const visibleMonitors = computed(() => [mainMonitor, ...monitors.value]);

function normalizeId(value) {
  return value === null || value === undefined ? '' : String(value);
}

function firstSelectableSensor() {
  for (const device of props.devices) {
    const sensor = (device.sensors || []).find((item) => item.status) || device.sensors?.[0];

    if (sensor) {
      return {
        device_id: normalizeId(device.id),
        sensor_id: normalizeId(sensor.id)
      };
    }
  }

  return {
    device_id: '',
    sensor_id: ''
  };
}

function availableSensors(monitor) {
  const device = props.devices.find((item) => Number(item.id) === Number(monitor.device_id));

  return device?.sensors || [];
}

function selectedSensor(monitor) {
  return availableSensors(monitor).find((sensor) => Number(sensor.id) === Number(monitor.sensor_id));
}

function selectedSensorName(monitor) {
  return selectedSensor(monitor)?.name || 'Sensor sin seleccionar';
}

function descriptorFor(monitor) {
  const window = queryByMonitor[monitor.id] || {};
  return { scope: 'public', sensorId: monitor.sensor_id, from: window.from, to: window.to, aggregation: 'raw' };
}

// Single composition point: historical window (graphSeriesQuery) + shared live tail
// (sensorReadings), merged by the pure projection helper. Neither store hydrates the other.
function composedFor(monitor) {
  const historical = graphSeriesQueryStore.resultForQuery(descriptorFor(monitor));
  return composeGraphSeries({
    historicalPoints: historical?.points || [],
    liveReadings: sensorReadingsStore.readingsFor(monitor.sensor_id),
    serverStats: historical?.stats || null,
    partial: Boolean(historical?.truncated || historical?.stats?.partial)
  });
}

function pointCount(monitor) {
  return composedFor(monitor).points.length;
}

function latestPoint(monitor) {
  const { points } = composedFor(monitor);
  return points[points.length - 1] || null;
}

function chartViewModel(monitor) {
  const composed = composedFor(monitor);
  // composed.points are chronological ascending; buildSensorChartViewModel expects newest-first
  // (it reverses internally), so hand it a reversed copy.
  const base = buildSensorChartViewModel([...composed.points].reverse(), { unit: selectedSensor(monitor)?.unit || '' });
  return { ...base, stats: composed.stats, partial: composed.partial };
}

function teardownRealtime(monitorId) {
  const handle = realtimeHandles.get(monitorId);

  if (handle) {
    handle.realtime.unsubscribeSensor();
    realtimeHandles.delete(monitorId);
  }
}

function teardownAllRealtime() {
  [...realtimeHandles.keys()].forEach(teardownRealtime);
}

function syncRealtime(monitor) {
  const sensorId = monitor.sensor_id;
  const existing = realtimeHandles.get(monitor.id);

  if (existing && existing.sensorId !== sensorId) {
    teardownRealtime(monitor.id);
  }

  if (!sensorId || !realtimeEnabled.value) {
    return;
  }

  if (!realtimeHandles.has(monitor.id)) {
    const realtime = useSensorRealtime(sensorId, (reading) => {
      sensorReadingsStore.mergeReading(sensorId, reading);
    });
    realtimeHandles.set(monitor.id, { realtime, sensorId });
  }

  realtimeHandles.get(monitor.id).realtime.subscribeSensor();
}

async function loadHistory(monitor) {
  const sensorId = monitor.sensor_id;

  if (!sensorId) {
    return;
  }

  loadingByMonitor[monitor.id] = true;
  readErrorByMonitor[monitor.id] = '';

  const to = new Date();
  const from = new Date(to.getTime() - RECOVERY_WINDOW_MS);
  // Record the exact window so descriptorFor(monitor) reads back the same cache entry we write.
  queryByMonitor[monitor.id] = { from, to };

  // Always the public graph-series contract: this board is the guest-capable graph surface
  // (PLAN.md 6.0/6.2), never a restricted/private sensor. The historical result stays in the query
  // store keyed by descriptor — it is NOT hydrated into the live tail (Gate 6: history and live
  // stay separate owners, composed only at render via composeGraphSeries).
  await graphSeriesQueryStore.fetchWindow(sensorId, { scope: 'public', from, to, consumerKey: monitor.id });

  const outcome = graphSeriesQueryStore.resultForQuery(descriptorFor(monitor));

  if (outcome?.error) {
    readErrorByMonitor[monitor.id] = outcome.error;
  }

  loadingByMonitor[monitor.id] = false;
}

function handleDeviceChange(monitor) {
  const firstSensor = availableSensors(monitor)[0];
  monitor.sensor_id = firstSensor ? normalizeId(firstSensor.id) : '';
  handleSensorChange(monitor);
}

async function handleSensorChange(monitor) {
  // Subscribe FIRST, then load history: history no longer touches the live store, so a live event
  // arriving mid-fetch lands in the shared tail and simply merges (dedup by id at compose time).
  syncRealtime(monitor);
  await loadHistory(monitor);
  schedulePersist();
}

function addMonitor() {
  const defaults = firstSelectableSensor();

  monitors.value.push({
    id: `chart-${Date.now()}`,
    device_id: defaults.device_id,
    sensor_id: defaults.sensor_id
  });

  nextTick(async () => {
    const monitor = monitors.value[monitors.value.length - 1];
    syncRealtime(monitor);
    await loadHistory(monitor);
    schedulePersist();
  });
}

function removeMonitor(monitorId) {
  teardownRealtime(monitorId);
  monitors.value = monitors.value.filter((monitor) => monitor.id !== monitorId);
  schedulePersist();
}

function moveMonitor(monitorId, direction) {
  const currentIndex = monitors.value.findIndex((monitor) => monitor.id === monitorId);
  const nextIndex = currentIndex + direction;

  if (currentIndex < 0 || nextIndex < 0 || nextIndex >= monitors.value.length) {
    return;
  }

  const nextMonitors = [...monitors.value];
  const [monitor] = nextMonitors.splice(currentIndex, 1);
  nextMonitors.splice(nextIndex, 0, monitor);
  monitors.value = nextMonitors;
  schedulePersist();
}

function currentLayout() {
  return {
    main: {
      device_id: mainMonitor.device_id || null,
      sensor_id: mainMonitor.sensor_id || null
    },
    monitors: monitors.value.map((monitor) => ({
      id: monitor.id,
      device_id: monitor.device_id || null,
      sensor_id: monitor.sensor_id || null
    }))
  };
}

function sanitizeMonitor(monitor, fallback = firstSelectableSensor()) {
  const deviceId = normalizeId(monitor?.device_id ?? fallback.device_id);
  const device = props.devices.find((item) => Number(item.id) === Number(deviceId));
  const sensorId = normalizeId(monitor?.sensor_id ?? fallback.sensor_id);
  const sensor = device?.sensors?.find((item) => Number(item.id) === Number(sensorId));

  if (device && sensor) {
    return {
      device_id: normalizeId(device.id),
      sensor_id: normalizeId(sensor.id)
    };
  }

  return fallback;
}

function readLocalLayout() {
  try {
    const raw = window.localStorage.getItem(LOCAL_STORAGE_KEY);

    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

async function loadSavedLayout() {
  if (authStore.isAuthenticated) {
    try {
      const response = await getDashboardPreferences();

      return response.data?.layout || null;
    } catch {
      return readLocalLayout();
    }
  }

  return readLocalLayout();
}

async function persistPreferences() {
  const layout = currentLayout();

  if (authStore.isAuthenticated) {
    try {
      await updateDashboardPreferences({ layout });
      return;
    } catch {
      window.localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(layout));
      return;
    }
  }

  window.localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(layout));
}

function schedulePersist() {
  if (restoring.value) {
    return;
  }

  window.clearTimeout(persistTimer);
  persistTimer = window.setTimeout(persistPreferences, 350);
}

async function restoreLayout() {
  if (props.devices.length === 0) {
    return;
  }

  restoring.value = true;

  const savedLayout = await loadSavedLayout();
  const defaultSelection = firstSelectableSensor();
  const mainSelection = sanitizeMonitor(savedLayout?.main, defaultSelection);

  mainMonitor.device_id = mainSelection.device_id;
  mainMonitor.sensor_id = mainSelection.sensor_id;
  monitors.value = Array.isArray(savedLayout?.monitors)
    ? savedLayout.monitors
      .filter((monitor) => monitor?.id)
      .map((monitor) => ({
        id: monitor.id,
        ...sanitizeMonitor(monitor, defaultSelection)
      }))
    : [];

  visibleMonitors.value.forEach(syncRealtime);
  await Promise.all(visibleMonitors.value.map((monitor) => loadHistory(monitor)));
  restoring.value = false;
  schedulePersist();
}

watch(realtimeEnabled, (enabled) => {
  if (enabled) {
    visibleMonitors.value.forEach(syncRealtime);
  } else {
    teardownAllRealtime();
  }
});
watch(() => props.devices, restoreLayout);

onMounted(restoreLayout);

onBeforeUnmount(() => {
  teardownAllRealtime();
  window.clearTimeout(persistTimer);
});
</script>
