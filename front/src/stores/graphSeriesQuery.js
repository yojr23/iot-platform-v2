import { defineStore } from 'pinia';

import { getGraphSeries } from '@/api/graph';
import { getSensorLatestReadings } from '@/api/sensors';
import { getApiErrorMessage, unwrapData } from '@/api/client';

// PLAN.md Stage 6.2 / pre-Stage-6 correction #6 — historical graph query layer, keyed by
// (authorizationScope, sensorId, from, to). Owns request identity/cancellation (aborts a superseded
// in-flight request instead of racing it), the returned source-set statistics, and immutable
// range hydration (each result records exactly the window it answers).
//
// Existing code reused: the 'public' branch is a thin client over `PublicGraphController`'s
// bounded series contract (agent A) via `getGraphSeries` — no query re-derives min/max/mean
// from a client-side slice, it stores the server's own `stats` object (PLAN.md 6.3: never
// re-cap an already-correct source set). The 'authenticated' branch adapts the existing
// `getSensorLatestReadings` endpoint for sensors that don't have an authenticated graph-series
// contract yet (private/restricted sensors, e.g. SensorDetailView) — this does NOT create a
// second latest-reading cache, it is a client-side view over the same endpoint.
//
// ponytail: an AbortController per query key lives in module scope, not store state —
// cancellation bookkeeping doesn't belong in Vue/Pinia reactivity, mirroring channelRegistry.js's
// module-scope refCounts convention.
const activeControllers = new Map();

function queryKey(scope, sensorId, from, to) {
  const fromKey = from ? new Date(from).getTime() : 'none';
  const toKey = to ? new Date(to).getTime() : 'none';
  return `${scope}:${sensorId}:${fromKey}:${toKey}`;
}

function computeStats(values) {
  if (!values.length) {
    return { min: null, max: null, mean: null, count: 0 };
  }

  return {
    min: Math.min(...values),
    max: Math.max(...values),
    mean: values.reduce((sum, value) => sum + value, 0) / values.length,
    count: values.length
  };
}

function adaptLatestReadings(readings) {
  const chronological = (Array.isArray(readings) ? readings : []).slice().reverse();
  const points = chronological.map((reading) => ({
    timestamp: reading.reading_time || reading.created_at,
    value: Number(reading.value),
    reading_id: reading.id ?? reading.reading_id
  }));

  return { points, stats: computeStats(points.map((point) => point.value)) };
}

function isCanceled(error) {
  return error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError';
}

export const useGraphSeriesQueryStore = defineStore('graphSeriesQuery', {
  state: () => ({
    byQuery: {} // fullKey -> { points, stats, window, scope, sensorId, loading, error }
  }),

  getters: {
    resultFor: (state) => (sensorId) => {
      const entries = Object.values(state.byQuery).filter(r => r.sensorId === sensorId);
      return entries.length > 0 ? entries[entries.length - 1] : null;
    }
  },

  actions: {
    /**
     * @param {number|string} sensorId
     * @param {{scope?: 'public'|'authenticated', from?: Date|string, to?: Date|string}} options
     * @returns {Promise<{points: Array, stats: object}|null>} null on cancellation or failure —
     *   check `resultFor(sensorId).error` to distinguish a real failure from a superseded call.
     */
    async fetchWindow(sensorId, { scope = 'public', from, to } = {}) {
      if (!sensorId) {
        return null;
      }

      const key = queryKey(scope, sensorId, from, to);
      activeControllers.get(key)?.abort();
      const controller = new AbortController();
      activeControllers.set(key, controller);

      this.byQuery = {
        ...this.byQuery,
        [key]: { ...(this.byQuery[key] || {}), loading: true, error: '', sensorId }
      };

      try {
        let result;

        if (scope === 'authenticated') {
          const response = await getSensorLatestReadings(sensorId, { limit: 20, signal: controller.signal });
          result = adaptLatestReadings(unwrapData(response));
        } else {
          const response = await getGraphSeries(sensorId, { from, to, signal: controller.signal });
          const payload = unwrapData(response) || {};
          result = {
            points: Array.isArray(payload.points) ? payload.points : [],
            stats: payload.stats || computeStats([]),
            truncated: Boolean(payload.truncated)
          };
        }

        this.byQuery = {
          ...this.byQuery,
          [key]: { points: result.points, stats: result.stats, truncated: result.truncated, window: { from, to }, scope, sensorId, loading: false, error: '' }
        };

        return result;
      } catch (error) {
        if (isCanceled(error)) {
          // Superseded by a newer call for the same key — that call already owns byQuery's
          // next write. Leave this store's state alone rather than racing it with an error.
          return null;
        }

        this.byQuery = {
          ...this.byQuery,
          [key]: {
            ...(this.byQuery[key] || {}),
            loading: false,
            sensorId,
            error: getApiErrorMessage(error, 'No se pudo cargar el historico del sensor.')
          }
        };
        return null;
      } finally {
        if (activeControllers.get(key) === controller) {
          activeControllers.delete(key);
        }
      }
    },

    clearSensor(sensorId) {
      const next = {};
      for (const [key, value] of Object.entries(this.byQuery)) {
        if (value.sensorId !== sensorId) {
          next[key] = value;
        }
      }
      this.byQuery = next;
    },

    clearAll() {
      activeControllers.forEach((controller) => controller.abort());
      activeControllers.clear();
      this.byQuery = {};
    }
  }
});
