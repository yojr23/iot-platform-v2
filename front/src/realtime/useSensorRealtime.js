import { ref, unref } from 'vue';

import { getEcho, onConnectionStateChange, onResync } from './echo';
import { listenOnChannel } from './channelRegistry';
import { getSensorLatestReadings } from '@/api/sensors';
import { unwrapData } from '@/api/client';
import { useAuthStore } from '@/stores/auth';

export const SENSOR_EVENT = 'NewSensorReading';
export const SENSOR_EVENT_CLASS = 'App\\Events\\NewSensorReading';

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

  async function runSnapshot() {
    // PLAN.md Stage 5.3 / ADR-2 lean V1: reuse the existing latest-readings REST endpoint
    // (already used for the view's initial load) as a one-shot bounded snapshot on
    // reconnect/visibility/auth change — never a periodic GET. The consumer's own
    // onReading merge (id-based dedup, e.g. SensorDetailView#addRealtimeReading) absorbs
    // overlap with buffered live events.
    if (!currentSensorId) {
      return;
    }

    try {
      const response = await getSensorLatestReadings(currentSensorId, { limit: 20 });
      const readings = unwrapData(response);

      (Array.isArray(readings) ? readings : []).forEach((reading) => {
        const normalized = normalizeReading(reading);

        if (normalized) {
          onReading?.(normalized);
        }
      });
    } catch {
      // ponytail: best-effort snapshot; the resubscribed live channel and the next
      // lifecycle trigger will catch up — deliberately not retried in a loop.
    }
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
    const privateChannel = useAuthStore().isAuthenticated;

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
        unsubscribeSensor();
        subscribeSensor();
        return;
      }

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
