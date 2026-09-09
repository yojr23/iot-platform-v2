import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const connectionWatchers = new Set();
const resyncWatchers = new Set();
const fetchActiveAlerts = vi.fn();
const listenOnChannel = vi.fn(() => () => {});

vi.mock('./echo', () => ({
  disconnectEcho: vi.fn(),
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

vi.mock('@/utils/sound', () => ({
  playAlertSound: vi.fn(),
}));

vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => fetchActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn(),
}));

vi.mock('@/api/client', () => ({
  clearStoredToken: vi.fn(),
  getApiErrorMessage: () => 'request failed',
  getStoredToken: vi.fn(() => null),
  setStoredToken: vi.fn(),
  unwrapData: (response) => response?.data,
}));

vi.mock('@/api/config', () => ({ getPublicConfig: vi.fn() }));

describe('alert recovery ownership', () => {
  beforeEach(async () => {
    vi.resetModules();
    connectionWatchers.clear();
    resyncWatchers.clear();
    fetchActiveAlerts.mockReset();
    listenOnChannel.mockClear();
    setActivePinia(createPinia());
  });

  afterEach(async () => {
    try {
      const { unsubscribeAlerts } = await import('./useAlertsRealtime');
      unsubscribeAlerts();
    } catch {
      // Some failure paths happen before the module is importable.
    }
  });

  it('keeps the projection disconnected when an invalidated recovery rejects', async () => {
    let rejectRequest;
    fetchActiveAlerts.mockImplementation(() => new Promise((_, reject) => {
      rejectRequest = reject;
    }));

    const { subscribeAlerts, unsubscribeAlerts } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');

    subscribeAlerts();
    for (const callback of [...resyncWatchers]) callback('reconnect');
    await vi.waitFor(() => expect(fetchActiveAlerts).toHaveBeenCalledOnce());
    unsubscribeAlerts();
    rejectRequest(new Error('network failure'));
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(useAlertsStore().realtimeStatus).toMatchObject({
      enabled: false,
      connected: false,
      mode: 'disconnected',
    });
  });

  it('does not report live while the initial connected transport still lacks a snapshot', async () => {
    let resolveRequest;
    fetchActiveAlerts.mockImplementation(() => new Promise((resolve) => {
      resolveRequest = resolve;
    }));

    const { subscribeAlerts, unsubscribeAlerts } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');

    subscribeAlerts();

    await vi.waitFor(() => expect(useAlertsStore().realtimeStatus).toMatchObject({
      connected: true,
      mode: 'recovering',
    }));

    unsubscribeAlerts();
    resolveRequest({ data: { alerts: [], count: 0 } });
    await new Promise((resolve) => setTimeout(resolve, 0));
  });

  it('does not apply a successful invalidated snapshot after unsubscribe', async () => {
    let resolveRequest;
    fetchActiveAlerts.mockImplementation(() => new Promise((resolve) => {
      resolveRequest = resolve;
    }));

    const { subscribeAlerts, unsubscribeAlerts } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');

    subscribeAlerts();
    await vi.waitFor(() => expect(fetchActiveAlerts).toHaveBeenCalledOnce());
    unsubscribeAlerts();
    resolveRequest({ data: { alerts: [{ id: 99 }], count: 1 } });
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(useAlertsStore().activeAlerts).toEqual([]);
    expect(useAlertsStore().realtimeStatus).toMatchObject({
      enabled: false,
      mode: 'disconnected',
    });
  });

  it('does not reopen the alerts channel on auth resync after logout', async () => {
    fetchActiveAlerts.mockResolvedValue({ data: { alerts: [], count: 0 } });
    const { subscribeAlerts } = await import('./useAlertsRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useAlertsStore } = await import('@/stores/alerts');
    const authStore = useAuthStore();
    const alertsStore = useAlertsStore();

    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };
    subscribeAlerts();
    expect(listenOnChannel).toHaveBeenCalledTimes(1);

    alertsStore.items = [{ id: 20 }];
    alertsStore.activeAlerts = [{ id: 20 }];
    alertsStore.unresolvedCount = 1;
    alertsStore.latestAlert = { id: 20 };
    authStore.clearAuth();

    for (const callback of [...resyncWatchers]) callback('auth');

    expect(listenOnChannel).toHaveBeenCalledTimes(1);
    expect(alertsStore.items).toEqual([]);
    expect(alertsStore.activeAlerts).toEqual([]);
    expect(alertsStore.unresolvedCount).toBe(0);
    expect(alertsStore.latestAlert).toBeNull();
    expect(alertsStore.realtimeStatus).toMatchObject({
      enabled: false,
      connected: false,
      mode: 'disconnected',
    });
  });

  it('reopens the alerts channel once on auth resync while still authenticated', async () => {
    fetchActiveAlerts.mockResolvedValue({ data: { alerts: [], count: 0 } });
    const { subscribeAlerts } = await import('./useAlertsRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();

    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };
    subscribeAlerts();
    expect(listenOnChannel).toHaveBeenCalledTimes(1);

    for (const callback of [...resyncWatchers]) callback('auth');

    expect(listenOnChannel).toHaveBeenCalledTimes(2);
  });
});
