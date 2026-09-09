import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const connectionWatchers = new Set();
const resyncWatchers = new Set();
const fetchActiveAlerts = vi.fn();

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
  listenOnChannel: () => () => {},
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
  getApiErrorMessage: () => 'request failed',
  unwrapData: (response) => response?.data,
}));

vi.mock('@/api/config', () => ({ getPublicConfig: vi.fn() }));

describe('alert recovery ownership', () => {
  beforeEach(async () => {
    vi.resetModules();
    connectionWatchers.clear();
    resyncWatchers.clear();
    fetchActiveAlerts.mockReset();
    setActivePinia(createPinia());
  });

  it('keeps the projection disconnected when an invalidated recovery rejects', async () => {
    let rejectRequest;
    fetchActiveAlerts.mockImplementation(() => new Promise((_, reject) => {
      rejectRequest = reject;
    }));

    const { subscribeAlerts, unsubscribeAlerts } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');

    subscribeAlerts();
    for (const callback of resyncWatchers) callback('reconnect');
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
    fetchActiveAlerts.mockImplementation(() => new Promise(() => {}));

    const { subscribeAlerts } = await import('./useAlertsRealtime');
    const { useAlertsStore } = await import('@/stores/alerts');

    subscribeAlerts();

    await vi.waitFor(() => expect(useAlertsStore().realtimeStatus).toMatchObject({
      connected: true,
      mode: 'recovering',
    }));
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
});
