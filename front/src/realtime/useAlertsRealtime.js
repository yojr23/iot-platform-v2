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

// S5-03: recovery buffer — live events that arrive while a snapshot GET is in flight are
// buffered here and replayed through the store's idempotency path after the snapshot applies.
// Bounded: max MAX_RECOVERY_EVENTS events OR MAX_RECOVERY_DURATION_MS wall-clock, whichever
// is hit first. Exceeding the bound marks projection stale and triggers a fresh recovery.
const recoveryBuffer = [];
let recovering = false;
let recoveryStartTime = 0;
const MAX_RECOVERY_EVENTS = 100;
const MAX_RECOVERY_DURATION_MS = 15_000;

function getEventPayload(event) {
  return event?.alert ?? event?.data ?? event;
}

// S5-06: transport state and projection freshness are separate concerns.
// 'live' = transport connected AND snapshot applied (projection fresh).
// 'recovering' = snapshot in flight, live events buffered.
// 'stale' = transport connected but snapshot not yet applied or failed.
// 'disconnected' = transport down.
function setStatus(status) {
  const alertsStore = useAlertsStore();
  alertsStore.setRealtimeStatus(status);
  isRealtimeEnabled.value = Boolean(status.enabled);
  isConnected.value = Boolean(status.connected);
  error.value = status.error || '';
}

const CONNECTION_ERROR_MESSAGES = {
  disconnected: 'Conexion en tiempo real perdida; reintentando.',
  unavailable: 'Conexion en tiempo real no disponible; reintentando.',
  error: 'Error de conexion en tiempo real.'
};

function replayRecoveryBuffer(alertsStore) {
  // S5-03: replay buffered live events through the store's idempotency path.
  // addRealtimeAlert already deduplicates by id (wasKnown check), so replay is safe.
  const buffered = recoveryBuffer.splice(0, recoveryBuffer.length);

  buffered.forEach((payload) => {
    const alert = alertsStore.addRealtimeAlert(payload);
    if (alert) {
      playAlertSound({
        enabled: alertsStore.soundEnabled,
        severity: alert?.alert_rule?.severity || payload?.severity
      });
    }
  });
}

async function runSnapshot(alertsStore) {
  // S5-03 / ADR-2 lean V1: one bounded, lifecycle-triggered snapshot. Buffer live events
  // during the in-flight request, then replay them after the snapshot applies. This prevents
  // the race where a live event is overwritten by an older HTTP snapshot.
  recovering = true;
  recoveryStartTime = Date.now();
  recoveryBuffer.length = 0;

  setStatus({
    enabled: true,
    connected: isConnected.value,
    mode: 'recovering',
    channel: ALERTS_CHANNEL,
    error: null
  });

  try {
    // S5-06: no .catch(() => []) — let HTTP failures propagate to the catch block below
    // so snapshot failure correctly marks the projection stale (not live/fresh).
    const newAlerts = await alertsStore.fetchActiveAlerts({ silent: true, notifyNew: true });

    newAlerts.forEach((alert) => {
      playAlertSound({
        enabled: alertsStore.soundEnabled,
        severity: alert?.alert_rule?.severity || alert?.severity
      });
    });

    // S5-03: replay buffered live events after snapshot applies
    replayRecoveryBuffer(alertsStore);

    // S5-06: mark projection fresh only if transport is also connected
    setStatus({
      enabled: true,
      connected: isConnected.value,
      mode: isConnected.value ? 'live' : 'stale',
      channel: ALERTS_CHANNEL,
      error: null
    });
  } catch {
    // S5-06: snapshot failure — mark stale/recovery_failed, NOT live
    recoveryBuffer.length = 0;
    setStatus({
      enabled: true,
      connected: isConnected.value,
      mode: 'stale',
      channel: ALERTS_CHANNEL,
      error: 'Error al recuperar alertas; estado posiblemente desactualizado.'
    });
  } finally {
    recovering = false;
    recoveryStartTime = 0;
  }
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

    // S5-03: if recovery is in flight, buffer the event and replay after snapshot applies.
    // This prevents the race where a live event arrives during the snapshot GET and is
    // overwritten when the older HTTP response arrives.
    if (recovering) {
      const age = Date.now() - recoveryStartTime;
      if (recoveryBuffer.length >= MAX_RECOVERY_EVENTS || age >= MAX_RECOVERY_DURATION_MS) {
        // Buffer bound exceeded — mark stale, clear buffer, let the snapshot finish
        recoveryBuffer.length = 0;
        recovering = false;
        setStatus({
          enabled: true,
          connected: isConnected.value,
          mode: 'stale',
          channel: ALERTS_CHANNEL,
          error: 'Buffer de recuperación excedido; estado posiblemente desactualizado.'
        });
        return;
      }
      recoveryBuffer.push(payload);
      return;
    }

    // Normal live path: apply directly via the store's idempotency owner (F2)
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
  }, { immediate: true });

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
  recovering = false;
  recoveryBuffer.length = 0;

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
