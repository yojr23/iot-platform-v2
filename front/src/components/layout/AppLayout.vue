<template>
  <LabShell>
    <slot />
    <AlertToast v-if="canViewAlerts" />
  </LabShell>
</template>

<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import LabShell from '@/components/dashboard/lab/LabShell.vue';

import AlertToast from '@/components/alerts/AlertToast.vue';
import { useAlertsRealtime } from '@/realtime/useAlertsRealtime';
import { useDeviceStatusRealtime } from '@/realtime/useDeviceStatusRealtime';
import { useAlertsStore } from '@/stores/alerts';
import { useAuthStore } from '@/stores/auth';
import { useDeviceStatusesStore } from '@/stores/deviceStatuses';
import { unlockAlertSound } from '@/utils/sound';

const authStore = useAuthStore();
const alertsStore = useAlertsStore();
const deviceStatusesStore = useDeviceStatusesStore();
const { subscribeAlerts, unsubscribeAlerts } = useAlertsRealtime();
const { subscribeDeviceStatus, unsubscribeDeviceStatus } = useDeviceStatusRealtime();
const canViewAlerts = computed(() => (
  authStore.isAuthenticated && authStore.can('alert.view')
));
const canViewDevices = computed(() => (
  authStore.isAuthenticated && authStore.can('device.view')
));
let startingGlobalAlerts = false;
let globalAlertsSubscribed = false;
let globalAlertsStartupGeneration = 0;

// Guest graph mode (unauthenticated visitors) must not touch the alert/config
// subsystem: no public alert-sound config fetch, no active-alerts fetch, no
// realtime subscribe, no polling loop. Only authenticated sessions get it.
// Gate 7 (7.3): useAlertsRealtime.subscribeAlerts() is the SOLE initial/reconnect snapshot
// owner (it fetches its own snapshot on subscribe/reconnect) — this component must not also
// fetch active alerts, or two snapshot owners race the same projection.
async function startGlobalAlerts() {
  if (startingGlobalAlerts) {
    return;
  }

  const startupGeneration = ++globalAlertsStartupGeneration;
  startingGlobalAlerts = true;

  try {
    // /config/runtime is a sanitized, authenticated-user contract. Alert viewers need its
    // sound preference; system_setting.view controls editing settings, not reading this value.
    await alertsStore.loadRuntimeConfig();
    if (startupGeneration !== globalAlertsStartupGeneration || !canViewAlerts.value) {
      return;
    }

    unlockAlertSound();

    const subscribed = subscribeAlerts();
    if (startupGeneration !== globalAlertsStartupGeneration || !canViewAlerts.value) {
      if (subscribed) {
        unsubscribeAlerts();
      }
      return;
    }

    globalAlertsSubscribed = subscribed;
  } finally {
    if (startupGeneration === globalAlertsStartupGeneration) {
      startingGlobalAlerts = false;
    }
  }
}

function stopGlobalAlerts() {
  globalAlertsStartupGeneration += 1;
  startingGlobalAlerts = false;
  if (globalAlertsSubscribed) {
    unsubscribeAlerts();
    globalAlertsSubscribed = false;
  }
  alertsStore.clearAuthorizedState();
}

// Device-status projection: same auth-gated lifecycle as alerts, but the adapter itself owns
// the authenticated-only guard and the auth-loss clear (Gate 8.4) — this shell just starts/
// stops it in step with the session so list/detail/dashboard share one live subscription.
function stopGlobalDeviceStatus() {
  unsubscribeDeviceStatus();
  deviceStatusesStore.clear();
}

watch(
  [canViewAlerts, canViewDevices],
  ([alertsAuthorized, devicesAuthorized]) => {
    if (alertsAuthorized) {
      startGlobalAlerts();
    } else {
      stopGlobalAlerts();
    }

    if (devicesAuthorized) {
      subscribeDeviceStatus();
    } else {
      stopGlobalDeviceStatus();
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  stopGlobalAlerts();
  stopGlobalDeviceStatus();
});
</script>
