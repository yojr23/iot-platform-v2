import { apiClient } from './client';

// PLAN.md Stage 6.2 — client for the public graph bootstrap/series contract added by
// PublicGraphController (back/app/Http/Controllers/Api/PublicGraphController.php). Anonymous,
// visibility-gated: bootstrap lists only public_monitoring_enabled sensors, series is scoped to
// one already-authorized sensor and a half-open [from,to) UTC window.
//
// Existing code reused: `apiClient` (front/src/api/client.js) for auth headers / 401 handling;
// no second Axios instance is created here.

/**
 * Formats a Date (or date-like value) into the exact second-precision UTC wire format the
 * backend validates: `YYYY-MM-DDTHH:mm:ssZ`. Milliseconds are truncated, never rounded, since
 * the contract is second-precision only.
 */
function toWindowParam(value) {
  const date = value instanceof Date ? value : new Date(value);
  return date.toISOString().replace(/\.\d+Z$/, 'Z');
}

export function getGraphBootstrap({ signal } = {}) {
  return apiClient.get('/public/graph/bootstrap', { signal });
}

export function getGraphSeries(sensorId, { from, to, signal } = {}) {
  return apiClient.get(`/public/graph/sensors/${sensorId}/series`, {
    params: { from: toWindowParam(from), to: toWindowParam(to) },
    signal
  });
}

// Authenticated counterpart of getGraphSeries for restricted (private) sensors. Same response
// shape ({points, stats, truncated}); the public route 404s for public_monitoring_enabled=false
// sensors, so an authenticated user querying a private sensor's history must use this one.
export function getPrivateGraphSeries(sensorId, { from, to, signal } = {}) {
  return apiClient.get(`/sensors/${sensorId}/series`, {
    params: { from: toWindowParam(from), to: toWindowParam(to) },
    signal
  });
}

/**
 * Shared adapter from a graph-series point (`{timestamp, value, reading_id}`) to the reading
 * shape the live sensor projection store (front/src/stores/sensorReadings.js) and
 * useSensorRealtime.js's onReading contract already expect. One mapping, reused by both the
 * historical query layer's consumers and the realtime recovery snapshot — avoids two places
 * quietly drifting on field names.
 */
export function graphPointToReading(point, sensorId) {
  return {
    id: point.reading_id,
    reading_id: point.reading_id,
    sensor_id: sensorId,
    value: point.value,
    reading_time: point.timestamp,
    created_at: point.timestamp
  };
}
