import { ref, unref } from 'vue';

import { getEcho, onConnectionStateChange, onResync } from './echo';
import { listenOnChannel } from './channelRegistry';
import { graphPointToReading } from '@/api/graph';
import { getStoredToken } from '@/api/client';
import { useGraphSeriesQueryStore } from '@/stores/graphSeriesQuery';
import { useSensorReadingsStore } from '@/stores/sensorReadings';

export const SENSOR_EVENT = 'NewSensorReading';
export const SENSOR_EVENT_CLASS = 'App\\Events\\NewSensorReading';

// PLAN.md Stage 6.2 — bounded lookback for the one-shot recovery snapshot below. ponytail: a
// fixed window, not a tracked cursor/gap-detector; good enough for ADR-2's lean V1 recovery
// (reconnect/visibility/auth triggers a bounded snapshot, never a periodic GET). Upgrade to a
// real cursor if ADR-2's full recovery path is ever selected instead.
export const RECOVERY_WINDOW_MS = 5 * 60 * 1000;

function resolveSensorId(sensorIdSource) {
  return typeof sensorIdSource === 'function' ? sensorIdSource() : unref(sensorIdSource);
}

function normalizeReading(payload) {
  const reading = payload?.reading ?? payload?.data ?? payload;

  if (!reading || typeof reading !== 'object') {
    return null;
  }

  return {
    id: reading.id ?? reading.reading_id,
    reading_id: reading.reading_id ?? reading.id,
    sensor_id: reading.sensor_id,
    value: reading.value,
    reading_time: reading.reading_time,
    created_at: reading.created_at ?? reading.reading_time,
    sensor_name: reading.sensor_name,
    sensor_type: reading.sensor_type,
    unit: reading.unit,
    device_name: reading.device_name,
    lab_name: reading.lab_name
  };
}

export function useSensorRealtime(sensorIdSource, onReading) {
  const isRealtimeEnabled = ref(false);
  const isConnected = ref(false);
  const error = ref('');

  let releaseChannel = null;
  let stopConnectionWatch = null;
  let stopResync = null;
  let subscribed = false;
  let currentSensorId = null;
  let currentPrivateChannel = false;

  async function runSnapshot() {
    // PLAN.md Stage 6.2: one-shot bounded snapshot on reconnect/visibility/auth change — never
    // a periodic GET — now sourced through the historical graph query layer instead of calling
    // an endpoint directly, so the same request-identity/cancellation owner also protects a
    // rapid reconnect+visibility double-fire from racing itself. The consumer's own onReading
    // merge (id-based dedup, e.g. SensorDetailView#addRealtimeReading /
    // sensorReadings store#mergeReading) absorbs overlap with buffered live events.
    if (!currentSensorId) {
      return;
    }

    const scope = currentPrivateChannel ? 'authenticated' : 'public';
    const to = new Date();
    const from = new Date(to.getTime() - RECOVERY_WINDOW_MS);

    const result = await useGraphSeriesQueryStore().fetchWindow(currentSensorId, { scope, from, to });

    (result?.points || []).forEach((point) => {
      onReading?.(graphPointToReading(point, currentSensorId));
    });
  }

  function subscribeSensor() {
    const sensorId = resolveSensorId(sensorIdSource);

    if (!sensorId || subscribed) {
      return false;
    }

    const echo = getEcho();

    if (!echo) {
      isRealtimeEnabled.value = false;
      isConnected.value = false;
      error.value = 'Pusher no configurado; detalle de sensor mantiene carga por API.';
      return false;
    }

    currentSensorId = sensorId;
    const channelName = `sensor.${sensorId}`;
    // Bug fix (pre-Gate-6): channel choice must track the SAME credential Echo itself uses
    // to build the /broadcasting/auth Authorization header (getStoredToken(), see echo.js),
    // not authStore.isAuthenticated (token && user). auth:changed fires the instant the token
    // is stored, before fetchUser() resolves `user` — isAuthenticated is still false at that
    // moment, so a mounted composable reacting to the 'auth' resync would wrongly rejoin the
    // public channel for an authenticated session.
    const privateChannel = Boolean(getStoredToken());
    currentPrivateChannel = privateChannel;

    releaseChannel = listenOnChannel(channelName, SENSOR_EVENT, (event) => {
      const reading = normalizeReading(event);

      if (!reading || Number(reading.sensor_id) !== Number(sensorId)) {
        return;
      }

      onReading?.(reading);
    }, { privateChannel });

    // S5-02: connected state now derives from the shared transport ack (echo.js) and
    // delivers the current state immediately to late subscribers via { immediate: true }.
    // A consumer created after Echo is already connected now sees isConnected=true right away
    // instead of waiting for the next transport transition.
    stopConnectionWatch = onConnectionStateChange((state) => {
      isConnected.value = state === 'connected';
      error.value = state === 'connected' ? '' : 'Conexion en tiempo real interrumpida; reintentando.';
    }, { immediate: true });

    stopResync = onResync((reason) => {
      if (reason === 'auth') {
        // A credential change can make a previously-visible private sensor inaccessible, and
        // this composable has no per-reading authorization bookkeeping of its own — drop the
        // shared live projection entirely (ownership item 1, sensorReadings.js#clearAll) before
        // resubscribing on whatever channel type the new credentials resolve to.
        unsubscribeSensor();
        useSensorReadingsStore().clearAll();
        subscribeSensor();
      }

      // Falls through for 'auth' too: subscribeSensor() above already refreshed
      // currentSensorId/currentPrivateChannel, so this repopulates whatever the new
      // subscription is actually authorized to see instead of leaving the chart blank.
      runSnapshot();
    });

    subscribed = true;
    isRealtimeEnabled.value = true;
    error.value = '';
    return true;
  }

  function unsubscribeSensor() {
    releaseChannel?.();
    stopConnectionWatch?.();
    stopResync?.();

    releaseChannel = null;
    stopConnectionWatch = null;
    stopResync = null;
    subscribed = false;
    currentSensorId = null;
    currentPrivateChannel = false;
    isConnected.value = false;
  }

  return {
    isRealtimeEnabled,
    isConnected,
    error,
    subscribeSensor,
    unsubscribeSensor
  };
}
