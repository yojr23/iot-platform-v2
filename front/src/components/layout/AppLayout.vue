<template>
  <div class="app-shell">
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
import { onBeforeUnmount, watch } from 'vue';

import AlertToast from '@/components/alerts/AlertToast.vue';
import { useAlertsRealtime } from '@/realtime/useAlertsRealtime';
import { useAlertsStore } from '@/stores/alerts';
import { useAuthStore } from '@/stores/auth';
import { playAlertSound, unlockAlertSound } from '@/utils/sound';

import NavBar from './NavBar.vue';

const authStore = useAuthStore();
const alertsStore = useAlertsStore();
const { subscribeAlerts, unsubscribeAlerts } = useAlertsRealtime();
let startingGlobalAlerts = false;
let globalAlertsSubscribed = false;
let globalAlertsStartupGeneration = 0;

async function refreshActiveAlerts({ notifyNew = false } = {}) {
  const newAlerts = await alertsStore.fetchActiveAlerts({ silent: true, notifyNew });

  newAlerts.forEach((alert) => {
    playAlertSound({
      enabled: alertsStore.soundEnabled,
      severity: alert?.alert_rule?.severity || alert?.severity
    });
  });
}

// Guest graph mode (unauthenticated visitors) must not touch the alert/config
// subsystem: no public alert-sound config fetch, no active-alerts fetch, no
// realtime subscribe, no polling loop. Only authenticated sessions get it.
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
    await refreshActiveAlerts();
    if (startupGeneration !== globalAlertsStartupGeneration || !authStore.isAuthenticated) {
      return;
    }

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

watch(
  () => authStore.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) {
      startGlobalAlerts();
      return;
    }

    stopGlobalAlerts();
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  stopGlobalAlerts();
});
</script>
