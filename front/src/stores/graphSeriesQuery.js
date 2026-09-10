import { defineStore } from 'pinia';

import { getGraphSeries } from '@/api/graph';
import { getApiErrorMessage, unwrapData } from '@/api/client';

// PLAN.md Stage 6.2 / Gate 6 — historical graph query layer. Owns HISTORICAL window results ONLY,
// window-addressable by the full descriptor (scope, sensorId, from, to, aggregation). Never a live
// tail: the authenticated latest-reading recovery branch that used to live here moved to
// useSensorRealtime.js (private recovery belongs with the subscription owner, not the history store).
//
// Existing code reused: the 'public' branch is a thin client over PublicGraphController's bounded
// series contract via `getGraphSeries` — it stores the server's own `stats`, it never re-derives
// min/max/mean from a client-side slice.
//
// ponytail: AbortControllers live in module scope, not store state — cancellation bookkeeping isn't
// Vue/Pinia reactive data, mirroring channelRegistry.js's module-scope refCounts convention.
// Cancellation is CONSUMER-keyed (one in-flight request per consumer), not query-keyed: a single
// monitor changing its window aborts its own previous request, while two monitors on the same sensor
// never cross-cancel. Results stay keyed by the full descriptor so both windows cache independently.
const activeControllersByConsumer = new Map();

/**
 * Canonical key owner for a historical graph query. The SAME function backs both the write path
 * (fetchWindow) and the read path (resultForQuery) so a cached result is always retrievable by the
 * exact descriptor that produced it.
 */
export function buildGraphQueryKey({ scope = 'public', sensorId, from, to, aggregation = 'raw' } = {}) {
  const fromKey = from ? new Date(from).getTime() : 'none';
  const toKey = to ? new Date(to).getTime() : 'none';
  return `${scope}:${sensorId}:${fromKey}:${toKey}:${aggregation}`;
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

function isCanceled(error) {
  return error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError';
}

export const useGraphSeriesQueryStore = defineStore('graphSeriesQuery', {
  state: () => ({
    byQuery: {} // buildGraphQueryKey(descriptor) -> { points, stats, truncated, window, scope, sensorId, aggregation, loading, error }
  }),

  getters: {
    resultForQuery: (state) => (descriptor) => state.byQuery[buildGraphQueryKey(descriptor)] || null
  },

  actions: {
    /**
     * @param {number|string} sensorId
     * @param {{scope?: 'public', from?: Date|string, to?: Date|string, aggregation?: string,
     *          consumerKey?: string}} options — `consumerKey` identifies the caller (e.g. a monitor
     *          id) so a superseding request from the SAME consumer aborts the previous one. Absent,
     *          it defaults to the full query key (per-query cancellation, no cross-consumer effect).
     * @returns {Promise<{points: Array, stats: object, truncated: boolean}|null>} null on
     *   cancellation or failure — check `resultForQuery(descriptor).error` to distinguish.
     */
    async fetchWindow(sensorId, { scope = 'public', from, to, aggregation = 'raw', consumerKey } = {}) {
      if (!sensorId) {
        return null;
      }

      const key = buildGraphQueryKey({ scope, sensorId, from, to, aggregation });
      const cancelKey = consumerKey ?? key;
      activeControllersByConsumer.get(cancelKey)?.abort();
      const controller = new AbortController();
      activeControllersByConsumer.set(cancelKey, controller);

      this.byQuery = {
        ...this.byQuery,
        [key]: { ...(this.byQuery[key] || {}), loading: true, error: '', sensorId }
      };

      try {
        const response = await getGraphSeries(sensorId, { from, to, signal: controller.signal });
        const payload = unwrapData(response) || {};
        const result = {
          points: Array.isArray(payload.points) ? payload.points : [],
          stats: payload.stats || computeStats([]),
          truncated: Boolean(payload.truncated)
        };

        this.byQuery = {
          ...this.byQuery,
          [key]: {
            points: result.points,
            stats: result.stats,
            truncated: result.truncated,
            window: { from, to },
            scope,
            sensorId,
            aggregation,
            loading: false,
            error: ''
          }
        };

        return result;
      } catch (error) {
        if (isCanceled(error)) {
          // Superseded by a newer call for the same consumer — that call owns the next write.
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
        if (activeControllersByConsumer.get(cancelKey) === controller) {
          activeControllersByConsumer.delete(cancelKey);
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
      activeControllersByConsumer.forEach((controller) => controller.abort());
      activeControllersByConsumer.clear();
      this.byQuery = {};
    }
  }
});
