import { defineStore } from 'pinia';

// PLAN.md Stage 6.2 / pre-Stage-6 correction #6 — live sensor-reading projection, keyed by
// `sensorId` (not by monitor/card id), so two monitors on the same sensor share one canonical
// tail instead of each keeping a private copy that can disagree.
//
// Existing code reused: this is SensorMonitorBoard.vue's pre-cutover normalize/merge/MAX_POINTS
// behavior (dedupe-by-id Map, chronological sort, bounded tail), moved here verbatim per the
// G0D ownership rule ("A shared sensor-readings projection store is allowed only by
// moving/reusing current SensorMonitorBoard.vue merge/history/MAX_POINTS behavior").
// `channelRegistry.js`/`useSensorRealtime.js` still own the Echo channel/subscription; this
// store only owns the data they feed it.
const MAX_POINTS = 60;

// Gate 6 hardening: reject rather than invent. A reading with no real id (id/reading_id both
// absent) is dropped — never synthesized from timestamp+value, which would defeat the id-based
// dedup that makes replayed/overlapping events idempotent. Unparsable timestamp or non-finite
// value are dropped too. Returns null for a rejected reading.
function normalizeReading(reading) {
  const id = reading.id ?? reading.reading_id;

  if (id === null || id === undefined) {
    return null;
  }

  const reading_time = reading.reading_time || reading.created_at || reading.timestamp;

  if (Number.isNaN(Date.parse(reading_time))) {
    return null;
  }

  const value = Number(reading.value);

  if (!Number.isFinite(value)) {
    return null;
  }

  return { id, value, reading_time };
}

function normalizeReadings(readings) {
  return (Array.isArray(readings) ? readings : [])
    .map(normalizeReading)
    .filter((reading) => reading !== null)
    // Deterministic order: timestamp ascending, then numeric-aware id as a stable tiebreak so two
    // readings sharing a timestamp always land in the same order regardless of insertion order.
    .sort((a, b) => (
      Date.parse(a.reading_time) - Date.parse(b.reading_time)
      || String(a.id).localeCompare(String(b.id), undefined, { numeric: true })
    ))
    .slice(-MAX_POINTS);
}

export const useSensorReadingsStore = defineStore('sensorReadings', {
  state: () => ({
    bySensor: {} // sensorId -> reading[] (chronological ascending, bounded to MAX_POINTS)
  }),

  getters: {
    readingsFor: (state) => (sensorId) => state.bySensor[sensorId] || [],
    latestFor: (state) => (sensorId) => {
      const readings = state.bySensor[sensorId] || [];
      return readings[readings.length - 1] || null;
    }
  },

  actions: {
    // Replace: a fresh historical hydration (e.g. a newly selected sensor, or a resolved query
    // window from the historical graph query layer). Immutable — never mutates readings in place.
    hydrate(sensorId, readings) {
      if (!sensorId) {
        return;
      }

      this.bySensor = { ...this.bySensor, [sensorId]: normalizeReadings(readings) };
    },

    // Merge: realtime append. Dedupes by id so a duplicate/replayed event collapses onto the
    // existing entry instead of appending a second point, and an out-of-order event is
    // re-sorted into place before the tail is re-bounded.
    mergeReading(sensorId, reading) {
      this.mergeReadings(sensorId, [reading]);
    },

    mergeReadings(sensorId, readings) {
      if (!sensorId) {
        return;
      }

      const current = this.bySensor[sensorId] || [];
      const byId = new Map(current.map((reading) => [reading.id, reading]));

      normalizeReadings(readings).forEach((reading) => {
        byId.set(reading.id, reading);
      });

      this.bySensor = { ...this.bySensor, [sensorId]: normalizeReadings(Array.from(byId.values())) };
    },

    clearSensor(sensorId) {
      const next = { ...this.bySensor };
      delete next[sensorId];
      this.bySensor = next;
    },

    // Ownership item 1 (PLAN.md Stage 6.2): clear inaccessible state on logout/scope revocation.
    // Called from useSensorRealtime.js's 'auth' resync — a credential change can make a
    // previously-visible private sensor inaccessible, and this store has no per-reading
    // authorization bookkeeping of its own, so the safe action is to drop every projection and
    // let the next subscribe/hydrate repopulate whatever the new credentials actually allow.
    clearAll() {
      this.bySensor = {};
    }
  }
});
