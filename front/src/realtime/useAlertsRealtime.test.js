import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const connectionWatchers = new Set();
const resyncWatchers = new Set();
const fetchActiveAlerts = vi.fn();
const channelCallbacks = new Map();
const listenOnChannel = vi.fn((channelName, eventName, callback) => {
  channelCallbacks.set(eventName, callback);
  return () => {};
});

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
    channelCallbacks.clear();
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
    expect(listenOnChannel).toHaveBeenCalledTimes(2);

    alertsStore.items = [{ id: 20 }];
    alertsStore.activeAlerts = [{ id: 20 }];
    alertsStore.unresolvedCount = 1;
    alertsStore.latestAlert = { id: 20 };
    authStore.clearAuth();

    for (const callback of [...resyncWatchers]) callback('auth');

    expect(listenOnChannel).toHaveBeenCalledTimes(2);
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
    expect(listenOnChannel).toHaveBeenCalledTimes(2);

    for (const callback of [...resyncWatchers]) callback('auth');

    expect(listenOnChannel).toHaveBeenCalledTimes(4);
  });
});

describe('Gate 7.2 replay-safe recovery buffer (triggered + resolved)', () => {
  beforeEach(async () => {
    vi.resetModules();
    connectionWatchers.clear();
    resyncWatchers.clear();
    channelCallbacks.clear();
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

  it('does not resurrect an alert resolved while the snapshot was in flight', async () => {
    let resolveSnapshot;
    fetchActiveAlerts.mockImplementation(() => new Promise((resolve) => {
      resolveSnapshot = resolve;
    }));

    const { subscribeAlerts, ALERTS_RESOLVED_EVENT } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');
    const alertsStore = useAlertsStore();

    subscribeAlerts();
    await vi.waitFor(() => expect(fetchActiveAlerts).toHaveBeenCalledOnce());

    // AlertResolved(50) arrives before the (older) HTTP snapshot response.
    channelCallbacks.get(ALERTS_RESOLVED_EVENT)({ alert: { id: 50 } });

    // The in-flight snapshot still reports alert 50 as active.
    resolveSnapshot({ data: { alerts: [{ id: 50 }], count: 1 } });
    await vi.waitFor(() => expect(alertsStore.realtimeStatus.mode).not.toBe('recovering'));

    expect(alertsStore.activeAlerts.some((alert) => Number(alert.id) === 50)).toBe(false);
    expect(alertsStore.unresolvedCount).toBe(0);
  });

  it('keeps the count stable when a trigger and its resolve both arrive during the same recovery', async () => {
    let resolveSnapshot;
    fetchActiveAlerts.mockImplementation(() => new Promise((resolve) => {
      resolveSnapshot = resolve;
    }));

    const { subscribeAlerts, ALERTS_EVENT, ALERTS_RESOLVED_EVENT } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');
    const alertsStore = useAlertsStore();

    subscribeAlerts();
    await vi.waitFor(() => expect(fetchActiveAlerts).toHaveBeenCalledOnce());

    channelCallbacks.get(ALERTS_EVENT)({ alert: { id: 60, resolved: false } });
    channelCallbacks.get(ALERTS_RESOLVED_EVENT)({ alert: { id: 60 } });

    resolveSnapshot({ data: { alerts: [], count: 0 } });
    await vi.waitFor(() => expect(alertsStore.realtimeStatus.mode).not.toBe('recovering'));

    expect(alertsStore.activeAlerts.some((alert) => Number(alert.id) === 60)).toBe(false);
    expect(alertsStore.unresolvedCount).toBe(0);
  });

  it('ignores a duplicate resolve delivered again after recovery has finished', async () => {
    fetchActiveAlerts.mockResolvedValue({ data: { alerts: [{ id: 70 }], count: 1 } });

    const { subscribeAlerts, ALERTS_RESOLVED_EVENT } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');
    const alertsStore = useAlertsStore();

    subscribeAlerts();
    await vi.waitFor(() => expect(alertsStore.realtimeStatus.mode).toBe('live'));
    expect(alertsStore.unresolvedCount).toBe(1);

    channelCallbacks.get(ALERTS_RESOLVED_EVENT)({ alert: { id: 70 } });
    expect(alertsStore.unresolvedCount).toBe(0);

    // Redelivery of the same resolved event must not double-decrement.
    channelCallbacks.get(ALERTS_RESOLVED_EVENT)({ alert: { id: 70 } });
    expect(alertsStore.unresolvedCount).toBe(0);
  });
});
