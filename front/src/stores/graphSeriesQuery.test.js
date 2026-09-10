import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/api/graph', () => ({
  getGraphSeries: vi.fn(),
}));

import { getGraphSeries } from '@/api/graph';
import { buildGraphQueryKey, useGraphSeriesQueryStore } from './graphSeriesQuery';

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
    const descriptor = { scope: 'public', sensorId: 5, from, to };
    const result = await store.fetchWindow(5, descriptor);

    expect(getGraphSeries).toHaveBeenCalledWith(5, { from, to, signal: expect.any(AbortSignal) });
    expect(result.points).toHaveLength(1);
    expect(store.resultForQuery(descriptor).stats.count).toBe(1);
    expect(store.resultForQuery(descriptor).error).toBe('');
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
    const from = new Date('2026-01-01T00:00:00Z');
    const to = new Date('2026-01-01T00:10:00Z');
    const descriptor = { scope: 'public', sensorId: 5, from, to };
    const result = await store.fetchWindow(5, descriptor);

    expect(result.points).toHaveLength(151);
    expect(result.stats.count).toBe(151);
    expect(store.resultForQuery(descriptor).stats.count).toBe(151);
  });

  it('caches two independent results for the same sensor across different windows', async () => {
    getGraphSeries.mockImplementation((sensorId, options) => Promise.resolve({
      data: {
        points: [],
        stats: { min: null, max: null, mean: null, count: options.from.getTime() }
      }
    }));

    const store = useGraphSeriesQueryStore();
    const to = new Date('2026-01-01T01:00:00Z');
    const fiveMin = { scope: 'public', sensorId: 5, from: new Date(to.getTime() - 5 * 60 * 1000), to };
    const dayLong = { scope: 'public', sensorId: 5, from: new Date(to.getTime() - 24 * 60 * 60 * 1000), to };

    await store.fetchWindow(5, { ...fiveMin, consumerKey: 'm1' });
    await store.fetchWindow(5, { ...dayLong, consumerKey: 'm2' });

    // Two distinct descriptors -> two distinct cache entries, neither overwriting the other.
    expect(store.resultForQuery(fiveMin).stats.count).toBe(fiveMin.from.getTime());
    expect(store.resultForQuery(dayLong).stats.count).toBe(dayLong.from.getTime());
    expect(buildGraphQueryKey(fiveMin)).not.toBe(buildGraphQueryKey(dayLong));
  });

  it('resultForQuery returns the exact result for the exact descriptor', async () => {
    getGraphSeries.mockResolvedValue({
      data: { points: [{ timestamp: '2026-01-01T00:00:01Z', value: 7, reading_id: 1 }], stats: { min: 7, max: 7, mean: 7, count: 1 } }
    });

    const store = useGraphSeriesQueryStore();
    const descriptor = { scope: 'public', sensorId: 9, from: new Date('2026-01-01T00:00:00Z'), to: new Date('2026-01-01T00:05:00Z'), aggregation: 'raw' };
    await store.fetchWindow(9, descriptor);

    expect(store.resultForQuery(descriptor).points[0].value).toBe(7);
    // A different aggregation is a different query -> no result.
    expect(store.resultForQuery({ ...descriptor, aggregation: 'avg' })).toBeNull();
  });

  it('same consumer changing window aborts its previous in-flight request', async () => {
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
    const to = new Date('2026-01-01T01:00:00Z');
    const firstCall = store.fetchWindow(5, { scope: 'public', from: new Date(to.getTime() - 5 * 60 * 1000), to, consumerKey: 'm1' });
    const secondCall = store.fetchWindow(5, { scope: 'public', from: new Date(to.getTime() - 60 * 60 * 1000), to, consumerKey: 'm1' });

    await Promise.all([firstCall, secondCall]);

    expect(firstSignal.aborted).toBe(true);
    expect(secondSignal.aborted).toBe(false);
  });

  it('two different consumers on the same sensor never cross-cancel', async () => {
    let firstSignal;
    getGraphSeries.mockImplementationOnce((sensorId, options) => {
      firstSignal = options.signal;
      return pendingUntilAborted(options.signal);
    });
    getGraphSeries.mockImplementationOnce(() =>
      Promise.resolve({ data: { points: [], stats: { min: null, max: null, mean: null, count: 0 } } }));

    const store = useGraphSeriesQueryStore();
    const from = new Date('2026-01-01T00:00:00Z');
    const to = new Date('2026-01-01T00:05:00Z');
    const firstCall = store.fetchWindow(5, { scope: 'public', from, to, consumerKey: 'm1' });
    const secondCall = store.fetchWindow(5, { scope: 'public', from, to, consumerKey: 'm2' });

    await secondCall;

    // m2 finishing must NOT abort m1 — different consumers.
    expect(firstSignal.aborted).toBe(false);

    store.clearAll();
    await firstCall.catch(() => {});
  });

  it('a canceled request does not overwrite state with an error', async () => {
    getGraphSeries.mockImplementationOnce((sensorId, options) => pendingUntilAborted(options.signal));
    getGraphSeries.mockImplementationOnce(() =>
      Promise.resolve({ data: { points: [], stats: { min: null, max: null, mean: null, count: 0 } } }));

    const store = useGraphSeriesQueryStore();
    const from = new Date('2026-01-01T00:00:00Z');
    const to = new Date('2026-01-01T00:05:00Z');
    const descriptor = { scope: 'public', sensorId: 5, from, to };
    const firstCall = store.fetchWindow(5, { ...descriptor, consumerKey: 'm1' });
    const secondCall = store.fetchWindow(5, { ...descriptor, consumerKey: 'm1' });

    const [firstResult] = await Promise.all([firstCall, secondCall]);

    expect(firstResult).toBeNull();
    expect(store.resultForQuery(descriptor).error).toBe('');
    expect(store.resultForQuery(descriptor).loading).toBe(false);
  });

  it('records a real failure as an error without leaving loading stuck true', async () => {
    getGraphSeries.mockRejectedValue(new Error('boom'));

    const store = useGraphSeriesQueryStore();
    const descriptor = { scope: 'public', sensorId: 5, from: new Date('2026-01-01T00:00:00Z'), to: new Date('2026-01-01T00:05:00Z') };
    const result = await store.fetchWindow(5, descriptor);

    expect(result).toBeNull();
    expect(store.resultForQuery(descriptor).loading).toBe(false);
    expect(store.resultForQuery(descriptor).error).toBeTruthy();
  });

  it('clearAll aborts pending requests and wipes every sensor result', async () => {
    getGraphSeries.mockImplementationOnce((sensorId, options) => pendingUntilAborted(options.signal));

    const store = useGraphSeriesQueryStore();
    const descriptor = { scope: 'public', sensorId: 5, from: new Date('2026-01-01T00:00:00Z'), to: new Date('2026-01-01T00:05:00Z') };
    store.fetchWindow(5, descriptor);
    store.clearAll();

    expect(store.resultForQuery(descriptor)).toBeNull();
  });
});
