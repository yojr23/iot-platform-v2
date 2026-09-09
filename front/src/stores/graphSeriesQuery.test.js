import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/api/graph', () => ({
  getGraphSeries: vi.fn(),
}));

vi.mock('@/api/sensors', () => ({
  getSensorLatestReadings: vi.fn(),
}));

import { getGraphSeries } from '@/api/graph';
import { getSensorLatestReadings } from '@/api/sensors';
import { useGraphSeriesQueryStore } from './graphSeriesQuery';

beforeEach(() => {
  setActivePinia(createPinia());
  vi.clearAllMocks();
});

// Mirrors what axios actually does when a request is aborted mid-flight (rejects with a
// CanceledError carrying code ERR_CANCELED) — a bare never-resolving Promise would just hang
// these tests forever once the store aborts it.
function pendingUntilAborted(signal) {
  return new Promise((resolve, reject) => {
    signal.addEventListener('abort', () => {
      const error = new Error('canceled');
      error.code = 'ERR_CANCELED';
      error.name = 'CanceledError';
      reject(error);
    });
  });
}

describe('graphSeriesQuery historical query layer', () => {
  it('fetches a public sensor window through the graph-series endpoint', async () => {
    getGraphSeries.mockResolvedValue({
      data: {
        points: [{ timestamp: '2026-01-01T00:00:01Z', value: 10, reading_id: 1 }],
        stats: { min: 10, max: 10, mean: 10, count: 1 }
      }
    });

    const store = useGraphSeriesQueryStore();
    const from = new Date('2026-01-01T00:00:00Z');
    const to = new Date('2026-01-01T00:05:00Z');
    const result = await store.fetchWindow(5, { scope: 'public', from, to });

    expect(getGraphSeries).toHaveBeenCalledWith(5, { from, to, signal: expect.any(AbortSignal) });
    expect(result.points).toHaveLength(1);
    expect(store.resultFor(5).stats.count).toBe(1);
    expect(store.resultFor(5).error).toBe('');
  });

  it('does not re-cap the server source-set stats at the legacy 60-point limit (PLAN.md 6.3)', async () => {
    const points = Array.from({ length: 151 }, (_, index) => ({
      timestamp: `2026-01-01T00:00:${String(index).padStart(2, '0')}Z`,
      value: index,
      reading_id: index
    }));
    getGraphSeries.mockResolvedValue({
      data: { points, stats: { min: 0, max: 150, mean: 75, count: 151 } }
    });

    const store = useGraphSeriesQueryStore();
    const result = await store.fetchWindow(5, { scope: 'public', from: new Date(), to: new Date() });

    expect(result.points).toHaveLength(151);
    expect(result.stats.count).toBe(151);
    expect(store.resultFor(5).stats.count).toBe(151);
  });

  it('falls back to the latest-readings adapter for the authenticated scope', async () => {
    getSensorLatestReadings.mockResolvedValue({
      data: [
        { id: 2, value: 20, reading_time: '2026-01-01T00:00:02Z' },
        { id: 1, value: 10, reading_time: '2026-01-01T00:00:01Z' }
      ]
    });

    const store = useGraphSeriesQueryStore();
    const result = await store.fetchWindow(5, { scope: 'authenticated' });

    expect(getSensorLatestReadings).toHaveBeenCalledWith(5, { limit: 20, signal: expect.any(AbortSignal) });
    expect(getGraphSeries).not.toHaveBeenCalled();
    // adaptLatestReadings reverses newest-first into chronological order
    expect(result.points.map((point) => point.reading_id)).toEqual([1, 2]);
    expect(result.stats).toEqual({ min: 10, max: 20, mean: 15, count: 2 });
  });

  it('aborts an in-flight request when a new window supersedes it for the same key', async () => {
    let firstSignal;
    let secondSignal;
    getGraphSeries.mockImplementationOnce((sensorId, options) => {
      firstSignal = options.signal;
      return pendingUntilAborted(options.signal);
    });
    getGraphSeries.mockImplementationOnce((sensorId, options) => {
      secondSignal = options.signal;
      return Promise.resolve({ data: { points: [], stats: { min: null, max: null, mean: null, count: 0 } } });
    });

    const store = useGraphSeriesQueryStore();
    const fixedFrom = new Date('2026-01-01T00:00:00Z');
    const fixedTo = new Date('2026-01-01T00:05:00Z');
    const firstCall = store.fetchWindow(5, { scope: 'public', from: fixedFrom, to: fixedTo });
    const secondCall = store.fetchWindow(5, { scope: 'public', from: fixedFrom, to: fixedTo });

    await Promise.all([firstCall, secondCall]);

    expect(firstSignal.aborted).toBe(true);
    expect(secondSignal.aborted).toBe(false);
  });

  it('a canceled request does not overwrite state with an error', async () => {
    getGraphSeries.mockImplementationOnce((sensorId, options) => pendingUntilAborted(options.signal));
    getGraphSeries.mockImplementationOnce(() =>
      Promise.resolve({ data: { points: [], stats: { min: null, max: null, mean: null, count: 0 } } }));

    const store = useGraphSeriesQueryStore();
    const firstCall = store.fetchWindow(5, { scope: 'public', from: new Date(), to: new Date() });
    const secondCall = store.fetchWindow(5, { scope: 'public', from: new Date(), to: new Date() });

    const [firstResult] = await Promise.all([firstCall, secondCall]);

    expect(firstResult).toBeNull();
    expect(store.resultFor(5).error).toBe('');
    expect(store.resultFor(5).loading).toBe(false);
  });

  it('records a real failure as an error without leaving loading stuck true', async () => {
    getGraphSeries.mockRejectedValue(new Error('boom'));

    const store = useGraphSeriesQueryStore();
    const result = await store.fetchWindow(5, { scope: 'public', from: new Date(), to: new Date() });

    expect(result).toBeNull();
    expect(store.resultFor(5).loading).toBe(false);
    expect(store.resultFor(5).error).toBeTruthy();
  });

  it('clearAll aborts pending requests and wipes every sensor result', async () => {
    getGraphSeries.mockImplementationOnce((sensorId, options) => pendingUntilAborted(options.signal));

    const store = useGraphSeriesQueryStore();
    store.fetchWindow(5, { scope: 'public', from: new Date(), to: new Date() });
    store.clearAll();

    expect(store.resultFor(5)).toBeNull();
  });
});
