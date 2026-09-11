<template>
  <LabShell v-if="usesLabShell">
    <slot />
    <AlertToast v-if="authStore.isAuthenticated" />
  </LabShell>
  <div v-else class="app-shell">
    <NavBar />

    <main class="app-main py-4">
      <div class="container-fluid px-3 px-lg-4">
        <slot />
      </div>
    </main>

    <AlertToast v-if="authStore.isAuthenticated" />

    <footer class="app-footer border-top py-3">
      <div class="container-fluid px-3 px-lg-4 small text-muted">
        iot-platform-v2
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import { useRoute } from 'vue-router';
import LabShell from '@/components/dashboard/lab/LabShell.vue';
const route = useRoute();
const usesLabShell = computed(() => [
  'dashboard', 'devices', 'device-detail', 'sensors', 'sensor-detail',
  'alerts', 'alert-detail', 'alert-rules',
  'config', 'config-general', 'config-alerts', 'config-email', 'config-diagnostics',
  'labs', 'sensor-types', 'device-types', 'users', 'metrics', 'profile'
].includes(route?.name));

import AlertToast from '@/components/alerts/AlertToast.vue';
import { useAlertsRealtime } from '@/realtime/useAlertsRealtime';
import { useDeviceStatusRealtime } from '@/realtime/useDeviceStatusRealtime';
import { useAlertsStore } from '@/stores/alerts';
import { useAuthStore } from '@/stores/auth';
import { useDeviceStatusesStore } from '@/stores/deviceStatuses';
import { unlockAlertSound } from '@/utils/sound';

import NavBar from './NavBar.vue';

const authStore = useAuthStore();
const alertsStore = useAlertsStore();
const deviceStatusesStore = useDeviceStatusesStore();
const { subscribeAlerts, unsubscribeAlerts } = useAlertsRealtime();
const { subscribeDeviceStatus, unsubscribeDeviceStatus } = useDeviceStatusRealtime();
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
    await alertsStore.loadRuntimeConfig();
    if (startupGeneration !== globalAlertsStartupGeneration || !authStore.isAuthenticated) {
      return;
    }

    unlockAlertSound();

    const subscribed = subscribeAlerts();
    if (startupGeneration !== globalAlertsStartupGeneration || !authStore.isAuthenticated) {
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
  () => authStore.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) {
      startGlobalAlerts();
      subscribeDeviceStatus();
      return;
    }

    stopGlobalAlerts();
    stopGlobalDeviceStatus();
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  stopGlobalAlerts();
  stopGlobalDeviceStatus();
});
</script>
