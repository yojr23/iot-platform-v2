import { ref } from 'vue';

import { disconnectEcho, getEcho } from './echo';
import { useAlertsStore } from '@/stores/alerts';
import { playAlertSound } from '@/utils/sound';

export const ALERTS_CHANNEL = 'alerts';
export const ALERTS_EVENT = 'NewAlertTriggered';
export const ALERTS_EVENT_CLASS = 'App\\Events\\NewAlertTriggered';

const isRealtimeEnabled = ref(false);
const isConnected = ref(false);
const error = ref('');

let channel = null;
let subscribed = false;
let connectionBound = false;
let activeEcho = null;
const seenAlertIds = new Set();

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

function bindConnectionState(echo) {
  const connection = echo?.connector?.pusher?.connection;

  if (!connection || connectionBound) {
    return;
  }

  connectionBound = true;

  connection.bind('connected', () => {
    setStatus({
      enabled: true,
      connected: true,
      mode: 'realtime',
      channel: ALERTS_CHANNEL,
      error: null
    });
  });

  connection.bind('disconnected', () => {
    setStatus({
      enabled: true,
      connected: false,
      mode: 'polling',
      channel: ALERTS_CHANNEL,
      error: 'Pusher desconectado; se mantiene polling.'
    });
  });

  connection.bind('unavailable', () => {
    setStatus({
      enabled: true,
      connected: false,
      mode: 'polling',
      channel: ALERTS_CHANNEL,
      error: 'Pusher no disponible; se mantiene polling.'
    });
  });

  connection.bind('error', (connectionError) => {
    setStatus({
      enabled: true,
      connected: false,
      mode: 'polling',
      channel: ALERTS_CHANNEL,
      error: connectionError?.error?.message || connectionError?.message || 'Error de conexion realtime.'
    });
  });
}

export function useAlertsRealtime() {
  const alertsStore = useAlertsStore();

  function subscribeAlerts() {
    if (subscribed) {
      return true;
    }

    const echo = getEcho();

    if (!echo) {
      setStatus({
        enabled: false,
        connected: false,
        mode: 'polling',
        channel: ALERTS_CHANNEL,
        error: 'Pusher no configurado; se mantiene polling.'
      });
      return false;
    }

    bindConnectionState(echo);
    activeEcho = echo;
    channel = echo.channel(ALERTS_CHANNEL);
    channel.listen(ALERTS_EVENT, (event) => {
      const payload = getEventPayload(event);
      const alertId = Number(payload?.id);

      if (Number.isFinite(alertId) && seenAlertIds.has(alertId)) {
        return;
      }

      if (Number.isFinite(alertId)) {
        seenAlertIds.add(alertId);
      }

      const alert = alertsStore.addRealtimeAlert(payload);
      playAlertSound({
        enabled: alertsStore.soundEnabled,
        severity: alert?.alert_rule?.severity || payload?.severity
      });
    });

    subscribed = true;
    setStatus({
      enabled: true,
      connected: false,
      mode: 'realtime',
      channel: ALERTS_CHANNEL,
      event: ALERTS_EVENT_CLASS,
      error: null
    });

    return true;
  }

  function unsubscribeAlerts() {
    if (activeEcho && channel) {
      activeEcho.leaveChannel(ALERTS_CHANNEL);
    }

    channel = null;
    subscribed = false;
    activeEcho = null;
    setStatus({
      enabled: false,
      connected: false,
      mode: 'polling',
      channel: ALERTS_CHANNEL,
      error: null
    });
  }

  function reconnect() {
    unsubscribeAlerts();
    disconnectEcho();
    connectionBound = false;
    return subscribeAlerts();
  }

  return {
    isRealtimeEnabled,
    isConnected,
    error,
    subscribeAlerts,
    unsubscribeAlerts,
    reconnect,
    start: subscribeAlerts,
    stop: unsubscribeAlerts
  };
}
