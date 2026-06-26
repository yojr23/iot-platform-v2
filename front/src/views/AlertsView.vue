<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="h3 mb-1">Alertas</h1>
        <p class="text-muted mb-0">Historial, estado activo y acciones de resolucion.</p>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />

    <AlertFilters
      v-model="filter"
      :loading="loading || resolvingAll"
      @refresh="load"
      @resolve-all="handleResolveAll"
    />

    <LoadingSpinner v-if="loading" label="Cargando alertas..." />

    <AlertList v-if="!loading" :alerts="alerts" :resolving-id="resolvingId" @resolve="handleResolve" />
  </section>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';

import { getActiveAlerts, getAlerts, getUnresolvedAlerts } from '@/api/alerts';
import { getApiErrorMessage } from '@/api/client';
import AlertFilters from '@/components/alerts/AlertFilters.vue';
import AlertList from '@/components/alerts/AlertList.vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { useAlertsStore } from '@/stores/alerts';
import { paginatedItems } from '@/utils/formatters';

const alertsStore = useAlertsStore();
const alerts = ref([]);
const loading = ref(false);
const error = ref('');
const success = ref('');
const filter = ref('all');
const resolvingId = ref(null);
const resolvingAll = ref(false);

async function load() {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    if (filter.value === 'active') {
      const response = await getActiveAlerts();
      alerts.value = response.data?.alerts || [];
      return;
    }

    const response = filter.value === 'unresolved'
      ? await getUnresolvedAlerts({ per_page: 50 })
      : await getAlerts({ per_page: 50 });

    alerts.value = paginatedItems(response);
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar las alertas.');
  } finally {
    loading.value = false;
  }
}

async function handleResolve(alert) {
  resolvingId.value = alert.id;
  error.value = '';
  success.value = '';

  try {
    await alertsStore.resolveAlert(alert.id);
    success.value = 'Alerta resuelta correctamente.';
    await load();
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo resolver la alerta.');
  } finally {
    resolvingId.value = null;
  }
}

async function handleResolveAll() {
  resolvingAll.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await alertsStore.resolveAll();
    success.value = response.data?.message || 'Alertas resueltas correctamente.';
    await load();
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron resolver las alertas.');
  } finally {
    resolvingAll.value = false;
  }
}

function mergeRealtimeAlert(alert) {
  if (!alert?.id) {
    return;
  }

  if (filter.value === 'active' || filter.value === 'unresolved') {
    if (alert.resolved) {
      return;
    }
  }

  const exists = alerts.value.some((item) => Number(item.id) === Number(alert.id));
  alerts.value = exists
    ? alerts.value.map((item) => (Number(item.id) === Number(alert.id) ? { ...item, ...alert } : item))
    : [alert, ...alerts.value].slice(0, 50);
}

watch(filter, load);
watch(() => alertsStore.latestAlert, mergeRealtimeAlert);
onMounted(load);
</script>
