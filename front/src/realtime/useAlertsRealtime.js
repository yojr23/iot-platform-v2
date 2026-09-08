import { ref } from 'vue';

import { disconnectEcho, getEcho, onConnectionStateChange, onResync } from './echo';
import { listenOnChannel } from './channelRegistry';
import { useAlertsStore } from '@/stores/alerts';
import { playAlertSound } from '@/utils/sound';

export const ALERTS_CHANNEL = 'alerts';
export const ALERTS_EVENT = 'NewAlertTriggered';
export const ALERTS_EVENT_CLASS = 'App\\Events\\NewAlertTriggered';

const isRealtimeEnabled = ref(false);
const isConnected = ref(false);
const error = ref('');

let releaseChannel = null;
let stopConnectionWatch = null;
let stopResync = null;
let subscribed = false;

function getEventPayload(event) {
  return event?.alert ?? event?.data ?? event;
}

function setStatus(status) {
  const alertsStore = useAlertsStore();
  alertsStore.setRealtimeStatus(status);
  isRealtimeEnabled.value = Boolean(status.enabled);
  isConnected.value = Boolean(status.connected);
  error.value = status.error || '';
}

// Honest connection vocabulary (audit.md §7 no-hybrid rule): this transport layer never
// claims a "polling" fallback mode of its own. AppLayout's separate reconciliation timer
// (kept until Stage 7 proves recovery) is a different layer's concern this module doesn't
// need to know about or misrepresent.
const CONNECTION_ERROR_MESSAGES = {
  disconnected: 'Conexion en tiempo real perdida; reintentando.',
  unavailable: 'Conexion en tiempo real no disponible; reintentando.',
  error: 'Error de conexion en tiempo real.'
};

async function runSnapshot(alertsStore) {
  // PLAN.md Stage 5.3 / ADR-2 lean V1: one bounded, lifecycle-triggered snapshot that reuses
  // the existing GET /alerts/active endpoint (already wired for the notifyNew flow) —
  // never a periodic GET loop. Dedup happens where it already lives: alertsStore.
  setStatus({
    enabled: true,
    connected: isConnected.value,
    mode: 'recovering',
    channel: ALERTS_CHANNEL,
    error: null
  });

  const newAlerts = await alertsStore
    .fetchActiveAlerts({ silent: true, notifyNew: true })
    .catch(() => []);

  newAlerts.forEach((alert) => {
    playAlertSound({
      enabled: alertsStore.soundEnabled,
      severity: alert?.alert_rule?.severity || alert?.severity
    });
  });

  setStatus({
    enabled: true,
    connected: isConnected.value,
    mode: isConnected.value ? 'live' : 'stale',
    channel: ALERTS_CHANNEL,
    error: null
  });
}

export function subscribeAlerts() {
  if (subscribed) {
    return true;
  }

  const alertsStore = useAlertsStore();
  const echo = getEcho();

  if (!echo) {
    setStatus({
      enabled: false,
      connected: false,
      mode: 'disconnected',
      channel: ALERTS_CHANNEL,
      error: 'Pusher no configurado.'
    });
    return false;
  }

  releaseChannel = listenOnChannel(ALERTS_CHANNEL, ALERTS_EVENT, (event) => {
    const payload = getEventPayload(event);
    // Idempotency lives in the store now (single alert-projection owner per PLAN.md Stage 5
    // / ownership F2) — the transport no longer keeps a parallel seenAlertIds dedup set.
    const alert = alertsStore.addRealtimeAlert(payload);

    if (alert) {
      playAlertSound({
        enabled: alertsStore.soundEnabled,
        severity: alert?.alert_rule?.severity || payload?.severity
      });
    }
  });

  stopConnectionWatch = onConnectionStateChange((state, connectionError) => {
    if (state === 'connected') {
      setStatus({ enabled: true, connected: true, mode: 'live', channel: ALERTS_CHANNEL, error: null });
      return;
    }

    setStatus({
      enabled: true,
      connected: false,
      mode: 'stale',
      channel: ALERTS_CHANNEL,
      error:
        connectionError?.error?.message
        || connectionError?.message
        || CONNECTION_ERROR_MESSAGES[state]
        || 'Conexion en tiempo real interrumpida.'
    });
  });

  stopResync = onResync((reason) => {
    if (reason === 'auth') {
      // The shared Echo instance was torn down by echo.js; rebuild our own subscription
      // against the fresh one instead of resyncing a now-dead channel.
      unsubscribeAlerts();
      subscribeAlerts();
      return;
    }

    runSnapshot(alertsStore);
  });

  subscribed = true;
  setStatus({
    enabled: true,
    connected: false,
    mode: 'connecting',
    channel: ALERTS_CHANNEL,
    event: ALERTS_EVENT_CLASS,
    error: null
  });

  return true;
}

export function unsubscribeAlerts() {
  releaseChannel?.();
  stopConnectionWatch?.();
  stopResync?.();

  releaseChannel = null;
  stopConnectionWatch = null;
  stopResync = null;
  subscribed = false;

  setStatus({
    enabled: false,
    connected: false,
    mode: 'disconnected',
    channel: ALERTS_CHANNEL,
    error: null
  });
}

export function reconnectAlerts() {
  unsubscribeAlerts();
  disconnectEcho();
  return subscribeAlerts();
}

export function useAlertsRealtime() {
  return {
    isRealtimeEnabled,
    isConnected,
    error,
    subscribeAlerts,
    unsubscribeAlerts,
    reconnect: reconnectAlerts,
    start: subscribeAlerts,
    stop: unsubscribeAlerts
  };
}
