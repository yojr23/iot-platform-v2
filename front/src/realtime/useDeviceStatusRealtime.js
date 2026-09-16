import { ref } from 'vue';

import { getEcho, onConnectionStateChange, onResync } from './echo';
import { listenOnChannel } from './channelRegistry';
import { getDeviceStatusSnapshot } from '@/api/devices';
import { useAuthStore } from '@/stores/auth';
import { useDeviceStatusesStore } from '@/stores/deviceStatuses';

export const DEVICE_STATUS_CHANNEL = 'device-status';
export const DEVICE_STATUS_EVENT = 'DeviceStatusUpdated';
export const DEVICE_STATUS_EVENT_CLASS = 'App\\Events\\DeviceStatusUpdated';

const isConnected = ref(false);
const error = ref('');

let releaseChannel = null;
let stopConnectionWatch = null;
let stopResync = null;
let subscribed = false;

// Gate 8.4: DeviceStatusUpdated events that arrive while the one-shot device snapshot GET is
// in flight are buffered and replayed afterwards. Recovery is single-flight and generation
// guarded; a bounded buffer requests one follow-up snapshot rather than allowing overlap.
let recoveryBuffer = [];
let activeRecovery = null;
let snapshotInFlight = false;
let recoveryRequested = false;
let recoveryGeneration = 0;
let projectionFresh = false;
const MAX_RECOVERY_EVENTS = 100;

function setStatus(store, status) {
  store.setRealtimeStatus(status);
  isConnected.value = Boolean(status.connected);
  error.value = status.error || '';
}

function eventPayload(event) {
  return event?.data ?? event;
}

async function runSnapshot(store, generation) {
  snapshotInFlight = true;
  // One-shot, lifecycle-triggered (subscribe/reconnect/visibility) — never a periodic GET.
  recoveryBuffer.length = 0;
  setStatus(store, {
    enabled: true,
    connected: isConnected.value,
    mode: 'recovering',
    channel: DEVICE_STATUS_CHANNEL,
    error: null
  });

  try {
    const devices = await fetchStatusSnapshot();
    if (generation !== recoveryGeneration) {
      return;
    }
    store.applySnapshot(devices, { authoritative: true });
    projectionFresh = true;
    setStatus(store, {
      enabled: true,
      connected: isConnected.value,
      mode: isConnected.value ? 'live' : 'stale',
      channel: DEVICE_STATUS_CHANNEL,
      error: null
    });
  } catch {
    if (generation !== recoveryGeneration) {
      return;
    }

    // Preserve live events buffered during the failed recovery (finally replays them), but make
    // the uncertainty explicit: a connected socket alone cannot certify the projection fresh.
    projectionFresh = false;
    setStatus(store, {
      enabled: true,
      connected: isConnected.value,
      mode: 'stale',
      channel: DEVICE_STATUS_CHANNEL,
      error: 'Error al recuperar el estado de dispositivos; estado posiblemente desactualizado.'
    });
  } finally {
    if (generation === recoveryGeneration) {
      snapshotInFlight = false;
      recoveryBuffer.splice(0, recoveryBuffer.length).forEach((payload) => store.applyStatusEvent(payload));
    }
  }
}

async function fetchStatusSnapshot() {
  const devices = [];
  const seenCursors = new Set();
  let cursor = null;

  do {
    const response = await getDeviceStatusSnapshot({ per_page: 100, ...(cursor ? { cursor } : {}) });
    const payload = response?.data ?? response ?? {};
    devices.push(...(Array.isArray(payload.data) ? payload.data : []));
    const nextCursor = Number(payload.next_cursor);
    cursor = Number.isFinite(nextCursor) && nextCursor > 0 ? nextCursor : null;
    if (cursor !== null && seenCursors.has(cursor)) {
      throw new Error('Device status snapshot cursor repeated.');
    }
    if (cursor !== null) {
      seenCursors.add(cursor);
    }
  } while (cursor !== null);

  return devices;
}

function requestSnapshot(store) {
  if (activeRecovery) {
    recoveryRequested = true;
    return activeRecovery;
  }

  const generation = ++recoveryGeneration;
  const recovery = runSnapshot(store, generation);
  activeRecovery = recovery;

  recovery.finally(() => {
    if (activeRecovery !== recovery) {
      return;
    }

    activeRecovery = null;
    if (recoveryRequested) {
      recoveryRequested = false;
      requestSnapshot(store);
    }
  });

  return recovery;
}

export function subscribeDeviceStatus() {
  if (subscribed) {
    return true;
  }

  // Authenticated only — device-status is a PrivateChannel, guests never subscribe.
  if (!useAuthStore().isAuthenticated) {
    return false;
  }

  const echo = getEcho();
  if (!echo) {
    error.value = 'Pusher no configurado; estado de dispositivos sin tiempo real.';
    return false;
  }

  const store = useDeviceStatusesStore();

  releaseChannel = listenOnChannel(DEVICE_STATUS_CHANNEL, DEVICE_STATUS_EVENT, (event) => {
    const payload = eventPayload(event);

    if (snapshotInFlight) {
      if (recoveryBuffer.length >= MAX_RECOVERY_EVENTS) {
        recoveryRequested = true;
        return;
      }
      recoveryBuffer.push(payload);
      return;
    }

    store.applyStatusEvent(payload);
  }, { privateChannel: true });

  if (!releaseChannel) {
    return false;
  }

  subscribed = true;

  stopConnectionWatch = onConnectionStateChange((state) => {
    if (state === 'connected') {
      setStatus(store, {
        enabled: true,
        connected: true,
        mode: projectionFresh ? 'live' : 'stale',
        channel: DEVICE_STATUS_CHANNEL,
        error: null
      });
      return;
    }

    projectionFresh = false;
    setStatus(store, {
      enabled: true,
      connected: false,
      mode: 'disconnected',
      channel: DEVICE_STATUS_CHANNEL,
      error: 'Conexion en tiempo real de dispositivos interrumpida.'
    });
  }, { immediate: true });

  // Subscribe FIRST (above), then fetch the snapshot — a live event during the fetch is
  // buffered above, not lost.
  requestSnapshot(store);

  stopResync = onResync((reason) => {
    if (reason === 'auth') {
      // Shared Echo instance was torn down by echo.js; rebuild against the fresh one.
      unsubscribeDeviceStatus();
      if (useAuthStore().isAuthenticated) {
        subscribeDeviceStatus();
      } else {
        store.clear();
      }
      return;
    }

    requestSnapshot(store);
  });

  return true;
}

export function unsubscribeDeviceStatus() {
  const store = useDeviceStatusesStore();
  releaseChannel?.();
  stopConnectionWatch?.();
  stopResync?.();

  releaseChannel = null;
  stopConnectionWatch = null;
  stopResync = null;
  subscribed = false;
  recoveryGeneration++;
  activeRecovery = null;
  snapshotInFlight = false;
  recoveryRequested = false;
  recoveryBuffer = [];
  projectionFresh = false;
  setStatus(store, {
    enabled: false,
    connected: false,
    mode: 'disconnected',
    channel: null,
    error: null
  });
}

export function useDeviceStatusRealtime() {
  return {
    isConnected,
    error,
    subscribeDeviceStatus,
    unsubscribeDeviceStatus,
    start: subscribeDeviceStatus,
    stop: unsubscribeDeviceStatus
  };
}
