import { ref } from 'vue';

import { getEcho, onConnectionStateChange, onResync } from './echo';
import { listenOnChannel } from './channelRegistry';
import { getDevices } from '@/api/devices';
import { paginatedItems } from '@/utils/formatters';
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
const MAX_RECOVERY_EVENTS = 100;

function eventPayload(event) {
  return event?.data ?? event;
}

async function runSnapshot(store, generation) {
  snapshotInFlight = true;
  // One-shot, lifecycle-triggered (subscribe/reconnect/visibility) — never a periodic GET.
  recoveryBuffer.length = 0;

  try {
    const response = await getDevices({ per_page: 100 });
    if (generation !== recoveryGeneration) {
      return;
    }
    store.applySnapshot(paginatedItems(response));
  } catch {
    // Lean V1: snapshot failure leaves whatever realtime already produced in place; the
    // channel stays subscribed so the next DeviceStatusUpdated still applies.
  } finally {
    if (generation === recoveryGeneration) {
      snapshotInFlight = false;
      recoveryBuffer.splice(0, recoveryBuffer.length).forEach((payload) => store.applyStatusEvent(payload));
    }
  }
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
  error.value = '';

  stopConnectionWatch = onConnectionStateChange((state) => {
    isConnected.value = state === 'connected';
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
  isConnected.value = false;
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
