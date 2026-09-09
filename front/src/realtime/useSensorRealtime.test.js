import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const connectionWatchers = new Set();
const resyncWatchers = new Set();

// Real channelRegistry is used (not mocked) so ref-count behavior for public vs private
// channels on the same sensor id is exercised for real — only the underlying Echo instance
// is faked, matching channelRegistry.test.js's pattern.
const echoMock = vi.hoisted(() => {
  const makeChannel = () => ({ listen: vi.fn(), stopListening: vi.fn() });
  const publicChannel = makeChannel();
  const privateChannel = makeChannel();
  const echo = {
    channel: vi.fn(() => publicChannel),
    private: vi.fn(() => privateChannel),
    leaveChannel: vi.fn(),
  };
  return { echo, publicChannel, privateChannel };
});

// Mutable per-test "stored token" backing the getStoredToken() mock below — this is the
// exact accessor echo.js itself reads to build the /broadcasting/auth Authorization header,
// and the fix under test makes useSensorRealtime pick its channel from the same source.
let storedToken = null;

vi.mock('./echo', () => ({
  getEcho: () => echoMock.echo,
  onConnectionStateChange: (callback, { immediate } = {}) => {
    connectionWatchers.add(callback);
    if (immediate) callback('connected');
    return () => connectionWatchers.delete(callback);
  },
  onResync: (callback) => {
    resyncWatchers.add(callback);
    return () => resyncWatchers.delete(callback);
  },
}));

vi.mock('@/api/client', () => ({
  clearStoredToken: vi.fn(),
  getStoredToken: vi.fn(() => storedToken),
  setStoredToken: vi.fn(),
  unwrapData: (response) => response?.data,
  // graphSeriesQuery.js's failure branch reads this; not exercised by any test in this file
  // today, but the real function signature is small enough to keep in sync here.
  getApiErrorMessage: (error, fallback) => error?.message || fallback,
}));

vi.mock('@/api/sensors', () => ({
  getSensorLatestReadings: vi.fn(() => Promise.resolve({ data: [] })),
}));

// Only getGraphSeries is a real network call worth mocking; graphPointToReading is a pure
// mapping helper and stays real so the snapshot tests below exercise the actual adapter.
vi.mock('@/api/graph', async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...actual,
    getGraphSeries: vi.fn(() => Promise.resolve({
      data: { points: [], stats: { min: null, max: null, mean: null, count: 0 } }
    })),
  };
});

function fireAuthResync() {
  for (const callback of [...resyncWatchers]) callback('auth');
}

// Flushes the whole pending microtask queue (not just one tick) so a chained
// runSnapshot() -> store.fetchWindow() -> api call await sequence fully settles before
// assertions run, without having to hand-count how many `await`s are in that chain.
const flushMicrotasks = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('sensor realtime channel privacy', () => {
  beforeEach(() => {
    vi.resetModules();
    storedToken = null;
    connectionWatchers.clear();
    resyncWatchers.clear();
    echoMock.echo.channel.mockClear();
    echoMock.echo.private.mockClear();
    echoMock.echo.leaveChannel.mockClear();
    echoMock.publicChannel.listen.mockClear();
    echoMock.publicChannel.stopListening.mockClear();
    echoMock.privateChannel.listen.mockClear();
    echoMock.privateChannel.stopListening.mockClear();
    setActivePinia(createPinia());
  });

  it('uses the public sensor channel for guests (no stored token)', async () => {
    const { useSensorRealtime } = await import('./useSensorRealtime');

    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();

    expect(echoMock.echo.channel).toHaveBeenCalledWith('sensor.7');
    expect(echoMock.echo.private).not.toHaveBeenCalled();
  });

  it('uses the private sensor channel when a token is stored, regardless of authStore.user hydration', async () => {
    storedToken = 'test-token';
    const { useSensorRealtime } = await import('./useSensorRealtime');
    // Deliberately do NOT set authStore.user here: this reproduces the exact race window
    // (token stored, user not yet hydrated) that caused the bug when the channel choice
    // was read from authStore.isAuthenticated instead of the stored token.
    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();

    expect(echoMock.echo.private).toHaveBeenCalledWith('sensor.7');
    expect(echoMock.echo.channel).not.toHaveBeenCalled();
  });

  it('guest -> login: releases the public channel and acquires the private channel exactly once', async () => {
    const { useSensorRealtime } = await import('./useSensorRealtime');
    const { getChannelRefCount } = await import('./channelRegistry');

    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();

    expect(getChannelRefCount('sensor.7')).toBe(1);
    expect(getChannelRefCount('sensor.7', { privateChannel: true })).toBe(0);

    // login: token now stored, then echo.js's 'auth' resync fires (setStoredToken's
    // auth:changed -> handleAuthChange -> notify(resyncListeners, 'auth')).
    storedToken = 'test-token';
    fireAuthResync();

    expect(getChannelRefCount('sensor.7')).toBe(0);
    expect(getChannelRefCount('sensor.7', { privateChannel: true })).toBe(1);
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledWith('sensor.7');
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledTimes(1);
    expect(echoMock.privateChannel.listen).toHaveBeenCalledTimes(1);
    expect(echoMock.publicChannel.listen).toHaveBeenCalledTimes(1);
  });

  it('authenticated -> logout: releases the private channel and acquires the public channel exactly once', async () => {
    storedToken = 'test-token';
    const { useSensorRealtime } = await import('./useSensorRealtime');
    const { getChannelRefCount } = await import('./channelRegistry');

    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();

    expect(getChannelRefCount('sensor.7', { privateChannel: true })).toBe(1);

    // logout: stored token cleared, then the 'auth' resync fires.
    storedToken = null;
    fireAuthResync();

    expect(getChannelRefCount('sensor.7', { privateChannel: true })).toBe(0);
    expect(getChannelRefCount('sensor.7')).toBe(1);
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledWith('private-sensor.7');
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledTimes(1);
    expect(echoMock.publicChannel.listen).toHaveBeenCalledTimes(1);
  });

  it('401 / token removal: the private channel is not reacquired on subsequent auth resyncs', async () => {
    storedToken = 'test-token';
    const { useSensorRealtime } = await import('./useSensorRealtime');
    const { getChannelRefCount } = await import('./channelRegistry');

    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();

    expect(echoMock.echo.private).toHaveBeenCalledTimes(1);

    // 401 clears the stored token; the resync fires once for the transition, and again
    // for e.g. a stray duplicate — neither should reacquire the private channel.
    storedToken = null;
    fireAuthResync();
    fireAuthResync();

    expect(echoMock.echo.private).toHaveBeenCalledTimes(1);
    expect(getChannelRefCount('sensor.7', { privateChannel: true })).toBe(0);
    expect(getChannelRefCount('sensor.7')).toBe(1);
  });
});

describe('sensor realtime recovery snapshot (graph-series adapter)', () => {
  beforeEach(() => {
    vi.resetModules();
    storedToken = null;
    connectionWatchers.clear();
    resyncWatchers.clear();
    echoMock.echo.channel.mockClear();
    echoMock.echo.private.mockClear();
    echoMock.echo.leaveChannel.mockClear();
    echoMock.publicChannel.listen.mockClear();
    echoMock.publicChannel.stopListening.mockClear();
    echoMock.privateChannel.listen.mockClear();
    echoMock.privateChannel.stopListening.mockClear();
    setActivePinia(createPinia());
  });

  it('uses the bounded graph-series adapter for a public sensor on a resync trigger', async () => {
    const { getGraphSeries } = await import('@/api/graph');
    getGraphSeries.mockResolvedValueOnce({
      data: {
        points: [{ timestamp: '2026-01-01T00:00:01Z', value: 42, reading_id: 9 }],
        stats: { min: 42, max: 42, mean: 42, count: 1 }
      }
    });

    const onReading = vi.fn();
    const { useSensorRealtime } = await import('./useSensorRealtime');
    const realtime = useSensorRealtime(7, onReading);
    realtime.subscribeSensor();

    for (const callback of [...resyncWatchers]) callback('visibility');
    await flushMicrotasks();

    expect(getGraphSeries).toHaveBeenCalledWith(
      7,
      expect.objectContaining({ from: expect.any(Date), to: expect.any(Date) })
    );
    expect(onReading).toHaveBeenCalledWith(expect.objectContaining({
      id: 9,
      reading_id: 9,
      sensor_id: 7,
      value: 42,
      reading_time: '2026-01-01T00:00:01Z'
    }));
  });

  it('falls back to the latest-readings adapter for a private sensor on a resync trigger', async () => {
    storedToken = 'test-token';
    const { getSensorLatestReadings } = await import('@/api/sensors');
    getSensorLatestReadings.mockResolvedValueOnce({
      data: [{ id: 3, value: 5, reading_time: '2026-01-01T00:00:05Z' }]
    });

    const onReading = vi.fn();
    const { useSensorRealtime } = await import('./useSensorRealtime');
    const realtime = useSensorRealtime(7, onReading);
    realtime.subscribeSensor();

    for (const callback of [...resyncWatchers]) callback('reconnect');
    await flushMicrotasks();

    expect(getSensorLatestReadings).toHaveBeenCalledWith(7, expect.objectContaining({ limit: 20 }));
    expect(onReading).toHaveBeenCalledWith(expect.objectContaining({ id: 3, sensor_id: 7, value: 5 }));
  });

  it('auth resync clears the shared live sensor projection (logout/scope revocation)', async () => {
    const { useSensorRealtime } = await import('./useSensorRealtime');
    const { useSensorReadingsStore } = await import('@/stores/sensorReadings');
    const projection = useSensorReadingsStore();
    projection.mergeReading(7, { id: 1, value: 1, reading_time: '2026-01-01T00:00:00Z' });
    expect(projection.readingsFor(7)).toHaveLength(1);

    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();
    fireAuthResync();

    expect(projection.readingsFor(7)).toHaveLength(0);
  });
});
