<template>
  <section class="dashboard-surface">
    <div class="dashboard-hero mb-4">
      <div>
        <p class="section-kicker mb-2">Vista publica de monitoreo</p>
        <h1 class="display-6 fw-semibold mb-2">Dashboard IoT</h1>
        <p class="mb-0">
          Supervisa sensores, graficas configurables, dispositivos y alertas activas en una sola pantalla.
        </p>
      </div>

      <div class="dashboard-hero__actions">
        <span class="badge rounded-pill text-bg-light">Estado: {{ summary.system_status || 'ok' }}</span>
        <button class="btn btn-light" type="button" :disabled="loading" @click="load">
          Actualizar
        </button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando metricas..." />

    <template v-if="!loading">
      <MetricsCards :summary="summary" />

      <SensorMonitorBoard
        class="mt-3"
        :devices="devices"
        :poll-interval="pollInterval"
      />

      <div class="row g-3 mt-1">
        <div class="col-12 col-xl-4">
          <ActiveAlertsCard />
        </div>

        <div class="col-12 col-xl-4">
          <DeviceStatusList :devices="devices.slice(0, 5)" />
        </div>

        <div class="col-12 col-xl-4">
          <RecentReadingsTable :readings="latestReadings" />
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import { getPublicConfig } from '@/api/config';
import { getPublicDashboardData } from '@/api/dashboard';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import ActiveAlertsCard from '@/components/dashboard/ActiveAlertsCard.vue';
import DeviceStatusList from '@/components/dashboard/DeviceStatusList.vue';
import MetricsCards from '@/components/dashboard/MetricsCards.vue';
import RecentReadingsTable from '@/components/dashboard/RecentReadingsTable.vue';
import SensorMonitorBoard from '@/components/dashboard/SensorMonitorBoard.vue';

const loading = ref(false);
const error = ref('');
const summary = ref({});
const devices = ref([]);
const pollInterval = ref(2000);

const latestReadings = computed(() => summary.value.latest_readings || summary.value.latestReadings || []);

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const [dashboardResponse, configResponse] = await Promise.all([
      getPublicDashboardData(),
      getPublicConfig()
    ]);
    const dashboardPayload = unwrapData(dashboardResponse) || {};
    const configPayload = unwrapData(configResponse) || {};

    summary.value = dashboardPayload;
    devices.value = Array.isArray(dashboardPayload.devices) ? dashboardPayload.devices : [];
    const configuredInterval = Number(configPayload.sensor_update_interval || 2000);
    pollInterval.value = Number.isFinite(configuredInterval) ? configuredInterval : 2000;
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar las metricas del dashboard.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
