import { formatDate } from '@/utils/formatters';

/**
 * Normalizes a raw (newest-first) sensor readings array into the chart
 * view-model contract shared by every sensor chart owner. Presentation
 * components must never re-derive labels/series/stats themselves.
 *
 * Honest V1 scope only: min/max/mean/count for the returned set and the
 * most recent reading timestamp. No threshold bands, staleness labels,
 * cadence/completeness/quality, or per-sensor precision rounding.
 *
 * @param {Array<{value:number|string, reading_time?:string, created_at?:string}>} readings
 * @param {{unit?: string}} [options]
 */
export function buildSensorChartViewModel(readings = [], { unit = '' } = {}) {
  const chronological = [...readings].reverse();
  const labels = chronological.map((reading) => formatDate(reading.reading_time || reading.created_at));
  const series = chronological.map((reading) => Number(reading.value ?? 0));

  const count = series.length;
  const stats = count > 0
    ? {
      min: Math.min(...series),
      max: Math.max(...series),
      mean: series.reduce((sum, value) => sum + value, 0) / count,
      count
    }
    : null;

  const lastReading = chronological[chronological.length - 1];
  const lastObservedAt = lastReading ? (lastReading.reading_time || lastReading.created_at || '') : '';

  return { labels, series, unit, stats, lastObservedAt };
}
