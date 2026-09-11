<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <p class="section-kicker text-primary mb-2">Observabilidad</p>
        <h1 class="h3 mb-1">Metricas del sistema</h1>
        <p class="text-muted mb-0">Estado de la plataforma IoT y rendimiento operativo.</p>
      </div>
      <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load">Actualizar</button>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando metricas..." />

    <template v-if="!loading">
      <!-- KPI row -->
      <div class="row g-3 mb-3">
        <div v-for="m in metricCards" :key="m.label" class="col-12 col-md-6 col-xl-3">
          <div class="content-panel p-3 h-100 metric-kpi" :style="{ '--kpi-accent': m.color }">
            <div class="d-flex align-items-center gap-3">
              <span class="metric-kpi-icon" :style="{ color: m.color }" v-html="m.icon" />
              <div class="flex-grow-1">
                <p class="text-muted small mb-1">{{ m.label }}</p>
                <p class="h4 mb-0" :style="{ color: m.value ? m.color : undefined }">{{ m.value }}</p>
                <p v-if="m.sub" class="text-muted small mb-0">{{ m.sub }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts row -->
      <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
          <div class="content-panel p-3 h-100">
            <h2 class="h6 text-muted mb-3">Dispositivos</h2>
            <div class="metric-chart">
              <Doughnut :data="devicesChart" :options="doughnutOptions" />
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="content-panel p-3 h-100">
            <h2 class="h6 text-muted mb-3">Reglas de alerta</h2>
            <div class="metric-chart">
              <Doughnut :data="rulesChart" :options="doughnutOptions" />
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="content-panel p-3 h-100">
            <h2 class="h6 text-muted mb-3">Disponibilidad</h2>
            <div class="metric-chart metric-gauge">
              <Doughnut :data="uptimeChart" :options="gaugeOptions" />
              <div class="metric-gauge-label" :style="{ color: uptimeColor }">
                <strong>{{ snap.uptime_percent }}%</strong>
                <small class="text-muted">uptime</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Comparison bar -->
      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="content-panel p-3">
            <h2 class="h6 text-muted mb-3">Comparativa del sistema</h2>
            <div class="metric-chart metric-chart-wide">
              <Bar :data="comparisonChart" :options="barOptions" />
            </div>
          </div>
        </div>
      </div>

      <!-- Raw detail tables -->
      <details class="content-panel p-3">
        <summary class="fw-semibold">Ver detalle numerico</summary>
        <div class="row g-3 mt-1">
          <div class="col-12 col-lg-6">
            <table class="table align-middle mb-0">
              <thead><tr><th>Dispositivos</th><th class="text-end">Valor</th></tr></thead>
              <tbody>
                <tr><td>Total dispositivos</td><td class="text-end fw-semibold">{{ snap.total_devices }}</td></tr>
                <tr><td>En linea</td><td class="text-end fw-semibold text-success">{{ snap.online_devices }}</td></tr>
                <tr><td>Fuera de linea</td><td class="text-end fw-semibold" :class="snap.offline_devices > 0 ? 'text-danger' : ''">{{ snap.offline_devices }}</td></tr>
                <tr><td>Total sensores</td><td class="text-end fw-semibold">{{ snap.total_sensors }}</td></tr>
                <tr><td>Lecturas hoy</td><td class="text-end fw-semibold">{{ snap.readings_today }}</td></tr>
              </tbody>
            </table>
          </div>
          <div class="col-12 col-lg-6">
            <table class="table align-middle mb-0">
              <thead><tr><th>Alertas y reglas</th><th class="text-end">Valor</th></tr></thead>
              <tbody>
                <tr><td>Alertas activas</td><td class="text-end fw-semibold" :class="snap.active_alerts > 0 ? 'text-danger' : 'text-success'">{{ snap.active_alerts }}</td></tr>
                <tr><td>Reglas totales</td><td class="text-end fw-semibold">{{ snap.total_alert_rules }}</td></tr>
                <tr><td>Reglas habilitadas</td><td class="text-end fw-semibold">{{ snap.enabled_rules }}</td></tr>
                <tr><td>Laboratorios</td><td class="text-end fw-semibold">{{ snap.total_labs }}</td></tr>
                <tr><td>Uptime</td><td class="text-end fw-semibold" :style="{ color: uptimeColor }">{{ snap.uptime_percent }}%</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </details>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
  Chart as ChartJS,
  ArcElement,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from 'chart.js';
import { Doughnut, Bar } from 'vue-chartjs';

import { getMetrics } from '@/api/metrics';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import { resolveZoneTokens } from '@/utils/chartTheme';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

// Status palette (CVD-validated, synced to the SINOA design tokens). line = solid hue.
const zone = resolveZoneTokens();
const SURFACE = '#ffffff'; // 2px gap between segments so adjacent fills read as separate.

const loading = ref(false);
const error = ref('');
const snapshot = ref({});
// Zero-safe view of the payload so every chart/card renders without NaN when data is missing.
const snap = computed(() => {
  const s = snapshot.value || {};
  return {
    total_devices: s.total_devices || 0,
    online_devices: s.online_devices || 0,
    offline_devices: s.offline_devices || 0,
    total_sensors: s.total_sensors || 0,
    readings_today: s.readings_today || 0,
    active_alerts: s.active_alerts || 0,
    total_alert_rules: s.total_alert_rules || 0,
    enabled_rules: s.enabled_rules || 0,
    total_labs: s.total_labs || 0,
    uptime_percent: s.uptime_percent || 0,
  };
});

const ICONS = {
  devices: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>',
  sensors: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg>',
  alerts: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
  uptime: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12A10 10 0 1 1 12 2"/><path d="M22 4 12 14.01l-3-3"/></svg>',
};

const uptimeColor = computed(() =>
  snap.value.uptime_percent >= 99 ? zone.normal.line
    : snap.value.uptime_percent >= 95 ? zone.warning.line
      : zone.danger.line,
);

const metricCards = computed(() => [
  {
    label: 'Dispositivos', value: snap.value.total_devices, color: zone.info.line, icon: ICONS.devices,
    sub: `${snap.value.online_devices} en linea · ${snap.value.offline_devices} fuera`,
  },
  {
    label: 'Sensores', value: snap.value.total_sensors, color: zone.info.line, icon: ICONS.sensors,
    sub: `${snap.value.readings_today} lecturas hoy`,
  },
  {
    label: 'Alertas activas', value: snap.value.active_alerts, icon: ICONS.alerts,
    color: snap.value.active_alerts > 0 ? zone.danger.line : zone.normal.line,
    sub: `${snap.value.total_alert_rules} reglas (${snap.value.enabled_rules} activas)`,
  },
  {
    label: 'Uptime', value: `${snap.value.uptime_percent}%`, color: uptimeColor.value, icon: ICONS.uptime,
    sub: `${snap.value.total_labs} laboratorios`,
  },
]);

const devicesChart = computed(() => ({
  labels: ['En linea', 'Fuera de linea'],
  datasets: [{
    data: [snap.value.online_devices, snap.value.offline_devices],
    backgroundColor: [zone.normal.line, zone.danger.line],
    borderColor: SURFACE, borderWidth: 2,
  }],
}));

const rulesChart = computed(() => {
  const disabled = Math.max(snap.value.total_alert_rules - snap.value.enabled_rules, 0);
  return {
    labels: ['Habilitadas', 'Inactivas'],
    datasets: [{
      data: [snap.value.enabled_rules, disabled],
      backgroundColor: [zone.info.line, zone.neutral.line],
      borderColor: SURFACE, borderWidth: 2,
    }],
  };
});

const uptimeChart = computed(() => ({
  labels: ['Uptime', 'Restante'],
  datasets: [{
    data: [snap.value.uptime_percent, Math.max(100 - snap.value.uptime_percent, 0)],
    backgroundColor: [uptimeColor.value, zone.neutral.fill],
    borderColor: SURFACE, borderWidth: 2,
  }],
}));

const comparisonChart = computed(() => ({
  labels: ['Dispositivos', 'Sensores', 'Reglas', 'Laboratorios'],
  datasets: [{
    label: 'Total',
    data: [snap.value.total_devices, snap.value.total_sensors, snap.value.total_alert_rules, snap.value.total_labs],
    backgroundColor: zone.info.line,
    borderRadius: 4, barThickness: 18,
  }],
}));

const doughnutOptions = {
  responsive: true, maintainAspectRatio: false, cutout: '62%',
  plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
};
// Half-ish gauge: single value arc, no legend (the center % label carries identity).
const gaugeOptions = {
  responsive: true, maintainAspectRatio: false, cutout: '74%', rotation: -90, circumference: 180,
  plugins: { legend: { display: false }, tooltip: { enabled: false } },
};
const barOptions = {
  indexAxis: 'y', responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
};

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const response = await getMetrics();
    snapshot.value = unwrapData(response) || {};
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar las metricas.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.metric-kpi {
  border-left: 4px solid var(--kpi-accent, var(--app-blue, #1d4ed8));
}
.metric-kpi-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--kpi-accent, #1d4ed8) 12%, transparent);
  flex: 0 0 auto;
}
.metric-chart {
  position: relative;
  height: 220px;
}
.metric-chart-wide {
  height: 240px;
}
.metric-gauge-label {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 14%;
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1.1;
  pointer-events: none;
}
.metric-gauge-label strong {
  font-size: 1.6rem;
}
</style>
