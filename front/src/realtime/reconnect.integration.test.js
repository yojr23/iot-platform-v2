import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const transport = vi.hoisted(() => {
  const handlers = new Map();
  const channel = {
    listen: vi.fn(),
    stopListening: vi.fn(),
  };
  const connection = {
    bind: vi.fn((event, callback) => handlers.set(event, callback)),
    emit(event) {
      handlers.get(event)?.();
    },
    reset() {
      handlers.clear();
      this.bind.mockClear();
    },
  };
  const Echo = vi.fn(function Echo() {
    return {
      channel: vi.fn(() => channel),
      leaveChannel: vi.fn(),
      disconnect: vi.fn(),
      connector: { pusher: { connection } },
    };
  });

  return { Echo, connection, channel };
});

const getActiveAlerts = vi.fn();

vi.mock('laravel-echo', () => ({ default: transport.Echo }));
vi.mock('pusher-js', () => ({ default: {} }));
vi.mock('@/api/client', () => ({
  clearStoredToken: vi.fn(),
  getStoredToken: () => 'test-token',
  getApiErrorMessage: () => 'request failed',
  setStoredToken: vi.fn(),
  unwrapData: (response) => response?.data,
}));
vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => getActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn(),
}));
vi.mock('@/api/config', () => ({ getPublicConfig: vi.fn(), getRuntimeConfig: vi.fn() }));
vi.mock('@/utils/sound', () => ({ playAlertSound: vi.fn() }));

describe('alert reconnect recovery', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.unstubAllEnvs();
    vi.stubEnv('VITE_PUSHER_APP_KEY', 'test-key');
    vi.stubEnv('VITE_PUSHER_APP_CLUSTER', 'mt1');
    transport.Echo.mockClear();
    transport.connection.reset();
    transport.channel.listen.mockClear();
    transport.channel.stopListening.mockClear();
    getActiveAlerts.mockReset();
    getActiveAlerts.mockResolvedValue({ data: { alerts: [], count: 0 } });
    setActivePinia(createPinia());
  });

  it('uses exactly one snapshot for initial connect and one more for reconnect', async () => {
    const pendingSnapshots = [];
    getActiveAlerts.mockImplementation(() => new Promise((resolve) => {
      pendingSnapshots.push(resolve);
    }));
    const { subscribeAlerts } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');
    subscribeAlerts();

    transport.connection.emit('connected');
    await vi.waitFor(() => expect(getActiveAlerts).toHaveBeenCalledTimes(1));
    pendingSnapshots.shift()({ data: { alerts: [], count: 0 } });
    await vi.waitFor(() => expect(useAlertsStore().realtimeStatus.mode).toBe('live'));

    transport.connection.emit('disconnected');
    transport.connection.emit('connected');
    await vi.waitFor(() => expect(getActiveAlerts).toHaveBeenCalledTimes(2));
    pendingSnapshots.shift()({ data: { alerts: [], count: 0 } });
    await new Promise((resolve) => setTimeout(resolve, 50));

    expect(getActiveAlerts).toHaveBeenCalledTimes(2);
  });
});
