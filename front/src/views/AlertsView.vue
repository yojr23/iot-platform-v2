<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">Alertas</h1>
        <p class="lab-resource-description">Revisa los eventos de tus sensores y gestiona las alertas pendientes.</p>
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
    <div v-if="!loading && hasMore" class="text-center mt-3">
      <BaseButton variant="outline-secondary" :loading="loadingMore" @click="loadNextPage">Cargar más</BaseButton>
    </div>
  </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { getActiveAlerts, getAlerts, getUnresolvedAlerts } from '@/api/alerts';
import { getApiErrorMessage } from '@/api/client';
import AlertFilters from '@/components/alerts/AlertFilters.vue';
import AlertList from '@/components/alerts/AlertList.vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { useAlertsStore } from '@/stores/alerts';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { paginatedItems } from '@/utils/formatters';

const alertsStore = useAlertsStore();
const loading = ref(false);
const error = ref('');
const success = ref('');
const filter = ref('all');
const resolvingId = ref(null);
const resolvingAll = ref(false);

const alertsList = usePaginatedList(
  (params) => {
    const extraParams = { per_page: 50, ...params };
    if (filter.value === 'unresolved') {
      return getUnresolvedAlerts(extraParams);
    }
    if (filter.value === 'active') {
      // active filter uses non-paginated endpoint
      return getActiveAlerts(extraParams);
    }
    return getAlerts(extraParams);
  },
  { perPage: 50 }
);
const alerts = alertsList.items;
const hasMore = alertsList.hasMore;
const loadingMore = alertsList.loadingMore;

// Wrapper for loadNextPage with error handling
async function loadNextPage() {
  if (alertsList.loadingMore.value || !alertsList.hasMore.value) {
    return;
  }
  try {
    await alertsList.loadNextPage();
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar más alertas.');
  }
}

async function load({ preserveSuccess = false } = {}) {
  loading.value = true;
  error.value = '';
  if (!preserveSuccess) {
    success.value = '';
  }

  try {
    if (filter.value === 'active') {
      const response = await getActiveAlerts();
      // reset() FIRST — it clears items (which is the same ref as `alerts`) plus page metadata so
      // no "Cargar más" shows for this non-paginated call. Assigning after avoids reset wiping it.
      alertsList.reset();
      alerts.value = response.data?.alerts || [];
      return;
    }

    const extraParams = {};
    if (filter.value === 'unresolved') {
      extraParams.status = 'unresolved';
    }
    await alertsList.loadFirstPage(extraParams);
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
    await load({ preserveSuccess: true });
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
    await load({ preserveSuccess: true });
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
    : [alert, ...alerts.value];
}

// DOCX / PLAN.md Stage 7: AlertResolved from another client must update the local list
// without requiring a manual refresh. markAlertResolved() removes the alert from
// activeAlerts and sets latestResolvedId, but AlertsView owns its own local `alerts` ref.
watch(() => alertsStore.latestResolvedId, (resolvedId) => {
  if (resolvedId == null) {
    return;
  }

  const id = Number(resolvedId);

  if (filter.value === 'all') {
    alerts.value = alerts.value.map((item) => (
      Number(item.id) === id
        ? { ...item, resolved: true }
        : item
    ));
    return;
  }

  alerts.value = alerts.value.filter(
    (item) => Number(item.id) !== id,
  );
});
watch(() => alertsStore.latestAlert, mergeRealtimeAlert);

watch(filter, load);
onMounted(load);
</script>
