import { ref, unref } from 'vue';

import { getEcho } from './echo';

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

  let channelName = null;
  let subscribed = false;
  let activeEcho = null;

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

    channelName = `sensor.${sensorId}`;
    activeEcho = echo;
    const channel = echo.channel(channelName);

    channel.listen(SENSOR_EVENT, (event) => {
      const reading = normalizeReading(event);

      if (!reading || Number(reading.sensor_id) !== Number(sensorId)) {
        return;
      }

      onReading?.(reading);
    });

    subscribed = true;
    isRealtimeEnabled.value = true;
    isConnected.value = true;
    error.value = '';
    return true;
  }

  function unsubscribeSensor() {
    if (activeEcho && channelName) {
      activeEcho.leaveChannel(channelName);
    }

    channelName = null;
    subscribed = false;
    activeEcho = null;
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
