<template>
  <div class="app-shell">
    <NavBar />

    <main class="app-main py-4">
      <div class="container-fluid px-3 px-lg-4">
        <slot />
      </div>
    </main>

    <AlertToast />

    <footer class="app-footer border-top py-3">
      <div class="container-fluid px-3 px-lg-4 small text-muted">
        iot-platform-v2
      </div>
    </footer>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

import AlertToast from '@/components/alerts/AlertToast.vue';
import { useAlertsRealtime } from '@/realtime/useAlertsRealtime';
import { useAlertsStore } from '@/stores/alerts';
import { playAlertSound, unlockAlertSound } from '@/utils/sound';

import NavBar from './NavBar.vue';

const alertsStore = useAlertsStore();
const { subscribeAlerts, unsubscribeAlerts } = useAlertsRealtime();
let globalAlertsPollingId = null;

async function refreshActiveAlerts({ notifyNew = false } = {}) {
  const newAlerts = await alertsStore.fetchActiveAlerts({ silent: true, notifyNew });

  newAlerts.forEach((alert) => {
    playAlertSound({
      enabled: alertsStore.soundEnabled,
      severity: alert?.alert_rule?.severity || alert?.severity
    });
  });
}

onMounted(async () => {
  await alertsStore.loadPublicConfig();
  unlockAlertSound();
  await refreshActiveAlerts();
  subscribeAlerts();
  globalAlertsPollingId = window.setInterval(() => {
    refreshActiveAlerts({ notifyNew: true });
  }, 10000);
});

onBeforeUnmount(() => {
  if (globalAlertsPollingId) {
    window.clearInterval(globalAlertsPollingId);
  }

  unsubscribeAlerts();
});
</script>
