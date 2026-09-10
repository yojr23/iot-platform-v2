// PLAN.md Stage 6.3 / Gate 6 — PURE composition of a historical window (graphSeriesQuery store) and
// the live tail (sensorReadings store) into one chart-ready point list. No HTTP, no Echo, no store
// access: it takes already-fetched inputs and returns a value. This is the ONLY place history and
// live merge for the chart — neither store hydrates the other (history never touches the live tail).

function toPoint(item) {
  // Accepts both graph-series points ({ timestamp, value, reading_id }) and live readings
  // ({ id, value, reading_time }) — the two shapes the two stores produce.
  const id = item.id ?? item.reading_id;
  const reading_time = item.reading_time ?? item.timestamp ?? item.created_at;
  return { id, value: item.value === null || item.value === undefined ? null : Number(item.value), reading_time };
}

function idCompare(a, b) {
  return String(a).localeCompare(String(b), undefined, { numeric: true });
}

export function computeStats(points) {
  const values = points.map((point) => point.value).filter((value) => Number.isFinite(value));

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

/**
 * Merge a historical window and the live tail by reading id (live wins on collision, so a live
 * event that overlaps the fetched window collapses onto its historical twin instead of doubling).
 * Sorted by timestamp, then numeric-aware id as a tiebreak.
 *
 * @param {{historicalPoints?: Array, liveReadings?: Array, serverStats?: object|null,
 *          partial?: boolean}} input
 * @returns {{points: Array, stats: object, partial: boolean}} When `partial`, stats are the
 *   server's own window stats (the returned points are only a sample and must NOT be re-summarized
 *   as if they were the full window); otherwise stats are computed over the merged points.
 */
export function composeGraphSeries({ historicalPoints = [], liveReadings = [], serverStats = null, partial = false } = {}) {
  const byId = new Map();

  historicalPoints.forEach((item) => {
    const point = toPoint(item);
    byId.set(String(point.id), point);
  });
  liveReadings.forEach((item) => {
    const point = toPoint(item);
    byId.set(String(point.id), point);
  });

  const points = [...byId.values()].sort((a, b) => {
    const delta = Date.parse(a.reading_time) - Date.parse(b.reading_time);
    return delta !== 0 ? delta : idCompare(a.id, b.id);
  });

  return {
    points,
    stats: partial ? serverStats : computeStats(points),
    partial
  };
}
