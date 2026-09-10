import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import { useSensorReadingsStore } from './sensorReadings';

beforeEach(() => {
  setActivePinia(createPinia());
});

describe('sensorReadings live projection store', () => {
  it('dedupes a duplicate/replayed reading id instead of appending a second point', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 1, value: 10, reading_time: '2026-01-01T00:00:01Z' });
    store.mergeReading(7, { id: 1, value: 99, reading_time: '2026-01-01T00:00:01Z' });

    expect(store.readingsFor(7)).toHaveLength(1);
    expect(store.readingsFor(7)[0].value).toBe(99);
  });

  it('rejects a reading with no real id instead of inventing one from timestamp/value', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { value: 10, reading_time: '2026-01-01T00:00:01Z' });

    expect(store.readingsFor(7)).toHaveLength(0);
  });

  it('rejects a reading with an unparsable timestamp', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 1, value: 10, reading_time: 'not-a-date' });

    expect(store.readingsFor(7)).toHaveLength(0);
  });

  it('rejects a reading whose value is not finite', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 1, value: 'abc', reading_time: '2026-01-01T00:00:01Z' });

    expect(store.readingsFor(7)).toHaveLength(0);
  });

  it('rejects a reading timestamped more than 60s in the future (device clock drift)', () => {
    const store = useSensorReadingsStore();
    const future = new Date(Date.now() + 61_000).toISOString();

    store.mergeReading(7, { id: 1, value: 10, reading_time: future });

    expect(store.readingsFor(7)).toHaveLength(0);
  });

  it('accepts a normal, non-future-dated reading', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 1, value: 10, reading_time: new Date().toISOString() });

    expect(store.readingsFor(7)).toHaveLength(1);
  });

  it('sorts readings sharing a timestamp by numeric-aware id', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 10, value: 10, reading_time: '2026-01-01T00:00:01Z' });
    store.mergeReading(7, { id: 2, value: 2, reading_time: '2026-01-01T00:00:01Z' });

    expect(store.readingsFor(7).map((reading) => reading.id)).toEqual([2, 10]);
  });

  it('re-orders an out-of-order reading chronologically instead of appending at the tail', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 2, value: 20, reading_time: '2026-01-01T00:00:02Z' });
    store.mergeReading(7, { id: 1, value: 10, reading_time: '2026-01-01T00:00:01Z' });

    expect(store.readingsFor(7).map((reading) => reading.id)).toEqual([1, 2]);
  });

  it('a replayed reading after hydration collapses onto the existing entry', () => {
    const store = useSensorReadingsStore();

    store.hydrate(7, [
      { id: 1, value: 1, reading_time: '2026-01-01T00:00:01Z' },
      { id: 2, value: 2, reading_time: '2026-01-01T00:00:02Z' }
    ]);
    store.mergeReading(7, { id: 1, value: 1, reading_time: '2026-01-01T00:00:01Z' });

    expect(store.readingsFor(7)).toHaveLength(2);
  });

  it('bounds the live tail to MAX_POINTS (60) after repeated merges', () => {
    const store = useSensorReadingsStore();

    for (let i = 0; i < 90; i += 1) {
      const readingTime = new Date(Date.UTC(2026, 0, 1, 0, 0, i)).toISOString();
      store.mergeReading(7, { id: i, value: i, reading_time: readingTime });
    }

    expect(store.readingsFor(7)).toHaveLength(60);
    expect(store.readingsFor(7)[0].id).toBe(30);
    expect(store.readingsFor(7)[59].id).toBe(89);
  });

  it('keeps two sensors independent', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 1, value: 1, reading_time: '2026-01-01T00:00:01Z' });
    store.mergeReading(8, { id: 1, value: 42, reading_time: '2026-01-01T00:00:01Z' });

    expect(store.readingsFor(7)).toHaveLength(1);
    expect(store.readingsFor(8)).toHaveLength(1);
    expect(store.readingsFor(7)[0].value).toBe(1);
    expect(store.readingsFor(8)[0].value).toBe(42);
  });

  it('latestFor returns the newest reading for a sensor, or null when empty', () => {
    const store = useSensorReadingsStore();

    expect(store.latestFor(7)).toBeNull();

    store.mergeReading(7, { id: 1, value: 1, reading_time: '2026-01-01T00:00:01Z' });
    store.mergeReading(7, { id: 2, value: 2, reading_time: '2026-01-01T00:00:02Z' });

    expect(store.latestFor(7).id).toBe(2);
  });

  it('clearAll wipes every sensor projection (logout/scope revocation)', () => {
    const store = useSensorReadingsStore();

    store.mergeReading(7, { id: 1, value: 1, reading_time: '2026-01-01T00:00:01Z' });
    store.mergeReading(8, { id: 1, value: 1, reading_time: '2026-01-01T00:00:01Z' });
    store.clearAll();

    expect(store.readingsFor(7)).toEqual([]);
    expect(store.readingsFor(8)).toEqual([]);
  });
});
