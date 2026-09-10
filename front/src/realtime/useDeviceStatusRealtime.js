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
// in flight are buffered and replayed afterwards. The store's sequence guard (Gate 8.3) makes
// replay order-independent/idempotent, so — unlike alerts — this buffer needs no kind-tagging
// or bounded-duration overflow fallback: worst case is a harmlessly-ignored duplicate.
let recoveryBuffer = [];
let snapshotInFlight = false;

function eventPayload(event) {
  return event?.data ?? event;
}

async function runSnapshot(store) {
  // One-shot, lifecycle-triggered (subscribe/reconnect/visibility) — never a periodic GET.
  snapshotInFlight = true;
  recoveryBuffer = [];

  try {
    const response = await getDevices({ per_page: 100 });
    store.applySnapshot(paginatedItems(response));
  } catch {
    // Lean V1: snapshot failure leaves whatever realtime already produced in place; the
    // channel stays subscribed so the next DeviceStatusUpdated still applies.
  } finally {
    snapshotInFlight = false;
    recoveryBuffer.splice(0, recoveryBuffer.length).forEach((payload) => store.applyStatusEvent(payload));
  }
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
  runSnapshot(store);

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

    runSnapshot(store);
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
  snapshotInFlight = false;
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
