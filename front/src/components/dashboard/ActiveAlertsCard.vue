<template>
  <section class="content-panel p-3 h-100">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
      <div>
        <h2 class="h5 mb-1">Alertas activas</h2>
        <p class="text-muted small mb-0">{{ updateMode }}</p>
      </div>
      <span class="badge text-bg-danger">{{ count }}</span>
    </div>

    <BaseAlert v-if="error" variant="warning" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando alertas..." />

    <div v-if="!loading && alerts.length === 0" class="text-muted small py-3">
      No hay alertas activas.
    </div>

    <ul v-if="alerts.length" class="list-group list-group-flush">
      <li v-for="alert in alerts" :key="alert.id" class="list-group-item px-0">
        <div class="d-flex justify-content-between gap-3">
          <div>
            <p class="fw-semibold mb-1">{{ alert.alert_rule?.message || alert.alert_rule?.name || `Alerta ${alert.id}` }}</p>
            <p class="small text-muted mb-0">
              {{ alert.sensor?.name || 'Sensor sin nombre' }} · {{ alert.device?.name || 'Dispositivo sin nombre' }}
            </p>
          </div>
          <span class="badge align-self-start" :class="severityClass(alert.alert_rule?.severity)">
            {{ severityLabel(alert.alert_rule?.severity) }}
          </span>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';

import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { useAlertsStore } from '@/stores/alerts';
import { severityLabel } from '@/utils/formatters';

const alertsStore = useAlertsStore();
const loading = computed(() => alertsStore.loading);
const error = computed(() => alertsStore.error);
const count = computed(() => alertsStore.unresolvedCount);
const alerts = computed(() => alertsStore.activeAlerts);
const updateMode = computed(() => (
  alertsStore.realtimeStatus.connected
    ? 'Tiempo real activo con polling de reconciliacion cada 5 segundos.'
    : 'Fallback por polling cada 5 segundos.'
));
let intervalId = null;

function severityClass(severity) {
  return severity === 'danger' ? 'text-bg-danger' : severity === 'warning' ? 'text-bg-warning' : 'text-bg-secondary';
}

async function load({ silent = false } = {}) {
  await alertsStore.fetchActiveAlerts({ silent });
}

onMounted(() => {
  load();
  intervalId = window.setInterval(() => load({ silent: true }), 5000);
});

onBeforeUnmount(() => {
  if (intervalId) {
    window.clearInterval(intervalId);
  }
});
</script>
