<template>
  <div class="toast-container position-fixed top-0 end-0 p-3 alert-toast-container">
    <Transition name="alert-toast-fade">
      <div
        v-if="visible && currentAlert"
        class="toast show shadow-sm"
        role="status"
        aria-live="polite"
        aria-atomic="true"
      >
        <div class="toast-header">
          <span class="badge me-2" :class="severityClass">{{ severityLabel(currentSeverity) }}</span>
          <strong class="me-auto">{{ currentTitle }}</strong>
          <small class="text-muted">{{ currentTime }}</small>
          <button type="button" class="btn-close ms-2 mb-1" aria-label="Cerrar" @click="close" />
        </div>
        <div class="toast-body">
          <p class="mb-1">{{ currentMessage }}</p>
          <p class="small text-muted mb-2">
            {{ currentDevice }} · {{ currentValue }}
          </p>
          <RouterLink class="btn btn-sm btn-outline-primary" to="/alerts" @click="close">
            Ver alertas
          </RouterLink>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { useAlertsStore } from '@/stores/alerts';
import { formatDate, formatNumber, severityLabel } from '@/utils/formatters';

const alertsStore = useAlertsStore();
const visible = ref(false);
const currentAlert = ref(null);
let hideTimer = null;

const currentSeverity = computed(() => currentAlert.value?.alert_rule?.severity || currentAlert.value?.severity || 'warning');
const severityClass = computed(() => {
  if (currentSeverity.value === 'danger') {
    return 'text-bg-danger';
  }

  if (currentSeverity.value === 'warning') {
    return 'text-bg-warning';
  }

  return 'text-bg-secondary';
});
const currentTitle = computed(() => currentAlert.value?.sensor?.name || currentAlert.value?.sensor_name || 'Alerta de sensor');
const currentMessage = computed(() => (
  currentAlert.value?.alert_rule?.message || currentAlert.value?.message || 'Se disparo una alerta.'
));
const currentDevice = computed(() => currentAlert.value?.device?.name || currentAlert.value?.device_name || 'Dispositivo no disponible');
const currentValue = computed(() => {
  const value = currentAlert.value?.sensor_reading?.value ?? currentAlert.value?.value;
  const unit = currentAlert.value?.sensor?.sensor_type?.unit || currentAlert.value?.unit || '';
  return value === null || value === undefined ? 'Valor no disponible' : `${formatNumber(value)} ${unit}`.trim();
});
const currentTime = computed(() => formatDate(currentAlert.value?.created_at || currentAlert.value?.timestamp));

function close() {
  visible.value = false;
  if (hideTimer) {
    window.clearTimeout(hideTimer);
    hideTimer = null;
  }
}

watch(
  () => alertsStore.latestAlert,
  (alert) => {
    if (!alert || !alertsStore.popupEnabled) {
      return;
    }

    currentAlert.value = alert;
    visible.value = true;

    if (hideTimer) {
      window.clearTimeout(hideTimer);
    }

    hideTimer = window.setTimeout(close, 9000);
  }
);

onBeforeUnmount(close);
</script>

<style scoped>
.alert-toast-fade-enter-active,
.alert-toast-fade-leave-active {
  transition: opacity 450ms ease, transform 450ms ease;
}

.alert-toast-fade-enter-from,
.alert-toast-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.alert-toast-fade-enter-to,
.alert-toast-fade-leave-from {
  opacity: 1;
  transform: translateY(0);
}
</style>
