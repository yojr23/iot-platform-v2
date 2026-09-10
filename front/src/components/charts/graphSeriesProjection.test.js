import { describe, expect, it } from 'vitest';

import { composeGraphSeries, computeStats } from './graphSeriesProjection';

const at = (second) => new Date(Date.UTC(2026, 0, 1, 0, 0, second)).toISOString();

describe('composeGraphSeries', () => {
  it('deterministic race: a live event that also lands in the resolved history appears exactly once', () => {
    // The board subscribes realtime FIRST (live event 101 buffered), THEN awaits history 1..100.
    // History may overlap the live event; the merge must collapse it, not double it.
    const historicalPoints = Array.from({ length: 100 }, (_, index) => ({
      reading_id: index + 1,
      value: index + 1,
      timestamp: at(index + 1)
    }));
    // history that also happens to include 101 (server captured it too) + the live tail carrying 101
    historicalPoints.push({ reading_id: 101, value: 101, timestamp: at(101) });
    const liveReadings = [{ id: 101, value: 101, reading_time: at(101) }];

    const { points } = composeGraphSeries({ historicalPoints, liveReadings });

    expect(points.filter((point) => Number(point.id) === 101)).toHaveLength(1);
    expect(points).toHaveLength(101);
    expect(points[points.length - 1].id).toBe(101);
  });

  it('live reading overwrites a historical point with the same id (live wins)', () => {
    const { points } = composeGraphSeries({
      historicalPoints: [{ reading_id: 1, value: 10, timestamp: at(1) }],
      liveReadings: [{ id: 1, value: 99, reading_time: at(1) }]
    });

    expect(points).toHaveLength(1);
    expect(points[0].value).toBe(99);
  });

  it('sorts by timestamp then numeric-aware id', () => {
    const { points } = composeGraphSeries({
      historicalPoints: [
        { reading_id: 20, value: 2, timestamp: at(5) },
        { reading_id: 3, value: 3, timestamp: at(5) }
      ],
      liveReadings: [{ id: 1, value: 1, reading_time: at(2) }]
    });

    expect(points.map((point) => point.id)).toEqual([1, 3, 20]);
  });

  it('computes stats over merged points when not partial', () => {
    const { stats, partial } = composeGraphSeries({
      historicalPoints: [
        { reading_id: 1, value: 10, timestamp: at(1) },
        { reading_id: 2, value: 30, timestamp: at(2) }
      ]
    });

    expect(partial).toBe(false);
    expect(stats).toEqual({ min: 10, max: 30, mean: 20, count: 2 });
  });

  it('returns the server stats verbatim when partial (does not re-summarize a truncated sample)', () => {
    const serverStats = { min: 0, max: 500, mean: 123, count: 5000 };
    const { stats, partial } = composeGraphSeries({
      historicalPoints: [{ reading_id: 1, value: 10, timestamp: at(1) }],
      serverStats,
      partial: true
    });

    expect(partial).toBe(true);
    expect(stats).toBe(serverStats);
  });

  it('computeStats ignores non-finite values and returns nulls for an empty set', () => {
    expect(computeStats([])).toEqual({ min: null, max: null, mean: null, count: 0 });
    expect(computeStats([{ value: 4 }, { value: Number.NaN }, { value: 6 }])).toEqual({ min: 4, max: 6, mean: 5, count: 2 });
  });
});
