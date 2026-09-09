import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const listenOnChannel = vi.fn(() => () => {});
const connectionWatchers = new Set();
const resyncWatchers = new Set();

vi.mock('./echo', () => ({
  getEcho: () => ({}),
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

vi.mock('./channelRegistry', () => ({
  listenOnChannel: (...args) => listenOnChannel(...args),
}));

vi.mock('@/api/client', () => ({
  clearStoredToken: vi.fn(),
  getStoredToken: vi.fn(() => null),
  setStoredToken: vi.fn(),
  unwrapData: (response) => response?.data,
}));

vi.mock('@/api/sensors', () => ({
  getSensorLatestReadings: vi.fn(() => Promise.resolve({ data: [] })),
}));

describe('sensor realtime channel privacy', () => {
  beforeEach(() => {
    vi.resetModules();
    listenOnChannel.mockClear();
    connectionWatchers.clear();
    resyncWatchers.clear();
    setActivePinia(createPinia());
  });

  it('uses the public sensor channel for guests', async () => {
    const { useSensorRealtime, SENSOR_EVENT } = await import('./useSensorRealtime');

    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();

    expect(listenOnChannel).toHaveBeenCalledWith(
      'sensor.7',
      SENSOR_EVENT,
      expect.any(Function),
      { privateChannel: false }
    );
  });

  it('uses the private sensor channel for authenticated users', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };
    const { useSensorRealtime, SENSOR_EVENT } = await import('./useSensorRealtime');

    const realtime = useSensorRealtime(7, vi.fn());
    realtime.subscribeSensor();

    expect(listenOnChannel).toHaveBeenCalledWith(
      'sensor.7',
      SENSOR_EVENT,
      expect.any(Function),
      { privateChannel: true }
    );
  });
});
