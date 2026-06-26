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
            <span class="badge" :class="selectedSensor(monitor)?.status ? 'text-bg-success' : 'text-bg-secondary'">
              {{ selectedSensor(monitor)?.status ? 'Activo' : 'Sin estado' }}
            </span>
            <span>{{ readingsFor(monitor).length }} puntos</span>
            <span v-if="latestReading(monitor)">
              Ultimo: {{ formatNumber(latestReading(monitor).value) }} {{ selectedSensor(monitor)?.unit || '' }}
            </span>
          </div>

          <BaseAlert
            v-if="readErrorByMonitor[monitor.id]"
            class="mt-3"
            variant="warning"
            :message="readErrorByMonitor[monitor.id]"
          />

          <div class="monitor-chart">
            <LoadingSpinner
              v-if="loadingByMonitor[monitor.id]"
              label="Cargando lecturas..."
            />
            <div v-else-if="readingsFor(monitor).length === 0" class="monitor-empty">
              Selecciona un sensor con lecturas para ver la grafica.
            </div>
            <Line v-else :data="chartData(monitor)" :options="chartOptions" />
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import {
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip
} from 'chart.js';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Line } from 'vue-chartjs';

import { getDashboardPreferences, updateDashboardPreferences } from '@/api/dashboard';
import { getSensorLatestReadings } from '@/api/sensors';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { useAuthStore } from '@/stores/auth';
import { formatDate, formatNumber } from '@/utils/formatters';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler);

const props = defineProps({
  devices: {
    type: Array,
    default: () => []
  },
  pollInterval: {
    type: Number,
    default: 2000
  }
});

const MAX_POINTS = 60;
const LOCAL_STORAGE_KEY = 'iot-platform-v2.dashboard_layout';
const authStore = useAuthStore();

const mainMonitor = reactive({
  id: 'main',
  device_id: '',
  sensor_id: ''
});
const monitors = ref([]);
const readingsByMonitor = ref({});
const loadingByMonitor = reactive({});
const readErrorByMonitor = reactive({});
const realtimeEnabled = ref(true);
const restoring = ref(false);

let pollTimer = null;
let persistTimer = null;

const visibleMonitors = computed(() => [mainMonitor, ...monitors.value]);

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false
  },
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    x: {
      ticks: {
        maxRotation: 0,
        autoSkip: true,
        maxTicksLimit: 6
      },
      grid: {
        display: false
      }
    },
    y: {
      beginAtZero: false,
      grid: {
        color: 'rgba(37, 99, 235, 0.1)'
      }
    }
  }
};

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

function readingsFor(monitor) {
  return readingsByMonitor.value[monitor.id] || [];
}

function latestReading(monitor) {
  const readings = readingsFor(monitor);

  return readings[readings.length - 1] || null;
}

function normalizeReading(reading) {
  const timestamp = reading.reading_time || reading.created_at || reading.time;

  return {
    id: reading.id ?? `${timestamp}-${reading.value}`,
    value: Number(reading.value),
    reading_time: timestamp
  };
}

function normalizeReadings(readings) {
  return (Array.isArray(readings) ? readings : [])
    .map(normalizeReading)
    .filter((reading) => Number.isFinite(reading.value) && reading.reading_time)
    .sort((a, b) => new Date(a.reading_time).getTime() - new Date(b.reading_time).getTime())
    .slice(-MAX_POINTS);
}

function setReadings(monitorId, readings) {
  readingsByMonitor.value = {
    ...readingsByMonitor.value,
    [monitorId]: normalizeReadings(readings)
  };
}

function mergeReadings(monitorId, readings) {
  const current = readingsByMonitor.value[monitorId] || [];
  const byId = new Map(current.map((reading) => [reading.id, reading]));

  normalizeReadings(readings).forEach((reading) => {
    byId.set(reading.id, reading);
  });

  setReadings(monitorId, Array.from(byId.values()));
}

function chartData(monitor) {
  const sensor = selectedSensor(monitor);
  const color = monitor.id === 'main' ? '#2563eb' : '#0ea5e9';
  const readings = readingsFor(monitor);

  return {
    labels: readings.map((reading) => formatDate(reading.reading_time)),
    datasets: [
      {
        label: sensor?.name || 'Valor',
        data: readings.map((reading) => reading.value),
        borderColor: color,
        backgroundColor: monitor.id === 'main' ? 'rgba(37, 99, 235, 0.16)' : 'rgba(14, 165, 233, 0.16)',
        pointBackgroundColor: '#ffffff',
        pointBorderColor: color,
        pointRadius: 3,
        tension: 0.32,
        fill: true
      }
    ]
  };
}

async function loadReadings(monitor, limit = MAX_POINTS) {
  if (!monitor.sensor_id) {
    setReadings(monitor.id, []);
    return;
  }

  loadingByMonitor[monitor.id] = true;
  readErrorByMonitor[monitor.id] = '';

  try {
    const response = await getSensorLatestReadings(monitor.sensor_id, { limit });
    setReadings(monitor.id, response.data || []);
  } catch {
    readErrorByMonitor[monitor.id] = 'No se pudieron cargar las lecturas del sensor.';
  } finally {
    loadingByMonitor[monitor.id] = false;
  }
}

async function refreshMonitor(monitor) {
  if (!monitor.sensor_id) {
    return;
  }

  try {
    const response = await getSensorLatestReadings(monitor.sensor_id, { limit: 1 });
    mergeReadings(monitor.id, response.data || []);
    readErrorByMonitor[monitor.id] = '';
  } catch {
    readErrorByMonitor[monitor.id] = 'No se pudo actualizar la ultima lectura.';
  }
}

function handleDeviceChange(monitor) {
  const firstSensor = availableSensors(monitor)[0];
  monitor.sensor_id = firstSensor ? normalizeId(firstSensor.id) : '';
  setReadings(monitor.id, []);
  handleSensorChange(monitor);
}

async function handleSensorChange(monitor) {
  await loadReadings(monitor);
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
    await loadReadings(monitors.value[monitors.value.length - 1]);
    schedulePersist();
  });
}

function removeMonitor(monitorId) {
  monitors.value = monitors.value.filter((monitor) => monitor.id !== monitorId);
  const nextReadings = { ...readingsByMonitor.value };
  delete nextReadings[monitorId];
  readingsByMonitor.value = nextReadings;
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

  await Promise.all(visibleMonitors.value.map((monitor) => loadReadings(monitor)));
  restoring.value = false;
  schedulePersist();
}

async function refreshVisibleMonitors() {
  await Promise.all(visibleMonitors.value.map(refreshMonitor));
}

function startPolling() {
  stopPolling();

  if (!realtimeEnabled.value) {
    return;
  }

  pollTimer = window.setInterval(refreshVisibleMonitors, Math.max(1000, props.pollInterval));
}

function stopPolling() {
  if (pollTimer) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
}

onMounted(async () => {
  await restoreLayout();
  startPolling();
});

onBeforeUnmount(() => {
  stopPolling();
  window.clearTimeout(persistTimer);
});

watch(realtimeEnabled, startPolling);
watch(() => props.devices, restoreLayout);
</script>
