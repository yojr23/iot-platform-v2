import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const connectionWatchers = new Set();
const resyncWatchers = new Set();
const getDeviceStatusSnapshot = vi.fn();

const echoMock = vi.hoisted(() => {
  const makeChannel = () => ({ listen: vi.fn(), stopListening: vi.fn() });
  const privateChannel = makeChannel();
  const echo = {
    channel: vi.fn(),
    private: vi.fn(() => privateChannel),
    leaveChannel: vi.fn(),
  };
  return { echo, privateChannel };
});

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

vi.mock('@/api/devices', () => ({
  getDeviceStatusSnapshot: (...args) => getDeviceStatusSnapshot(...args),
}));

function fireResync(reason) {
  for (const callback of [...resyncWatchers]) callback(reason);
}

function authenticate(authStore) {
  authStore.token = 'test-token';
  authStore.user = { id: 1, name: 'Test User' };
}

describe('device status realtime adapter', () => {
  beforeEach(() => {
    vi.resetModules();
    connectionWatchers.clear();
    resyncWatchers.clear();
    echoMock.echo.channel.mockClear();
    echoMock.echo.private.mockClear();
    echoMock.echo.leaveChannel.mockClear();
    echoMock.privateChannel.listen.mockClear();
    echoMock.privateChannel.stopListening.mockClear();
    getDeviceStatusSnapshot.mockReset();
    getDeviceStatusSnapshot.mockResolvedValue({ data: { data: [], next_cursor: null } });
    setActivePinia(createPinia());
  });

  afterEach(async () => {
    const { unsubscribeDeviceStatus } = await import('./useDeviceStatusRealtime');
    unsubscribeDeviceStatus();
  });

  it('never subscribes for a guest (unauthenticated)', async () => {
    const { subscribeDeviceStatus } = await import('./useDeviceStatusRealtime');

    const subscribed = subscribeDeviceStatus();

    expect(subscribed).toBe(false);
    expect(echoMock.echo.private).not.toHaveBeenCalled();
    expect(getDeviceStatusSnapshot).not.toHaveBeenCalled();
  });

  it('subscribes on the private device-status channel with ref-count 1, released on unsubscribe', async () => {
    const { subscribeDeviceStatus, unsubscribeDeviceStatus } = await import('./useDeviceStatusRealtime');
    const { getChannelRefCount } = await import('./channelRegistry');
    const { useAuthStore } = await import('@/stores/auth');
    authenticate(useAuthStore());

    subscribeDeviceStatus();

    expect(echoMock.echo.private).toHaveBeenCalledWith('device-status');
    expect(getChannelRefCount('device-status', { privateChannel: true })).toBe(1);

    unsubscribeDeviceStatus();

    expect(getChannelRefCount('device-status', { privateChannel: true })).toBe(0);
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledWith('private-device-status');
  });

  it('buffers a live event that arrives during the in-flight snapshot and applies it after', async () => {
    let resolveSnapshot;
    getDeviceStatusSnapshot.mockImplementation(() => new Promise((resolve) => {
      resolveSnapshot = resolve;
    }));

    const { subscribeDeviceStatus, DEVICE_STATUS_EVENT } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledOnce());

    // The device metadata snapshot for device 1 is still "active" while a live event
    // already reports it went inactive — the race this buffer must survive.
    const listenCall = echoMock.privateChannel.listen.mock.calls.find(([event]) => event === DEVICE_STATUS_EVENT);
    const deliver = listenCall[1];
    deliver({ device_id: 1, event_sequence: 5, status: false, is_active: false });

    // Not lost, but not applied yet either (snapshot still in flight).
    expect(store.statusFor(1)).toBeNull();

    resolveSnapshot({ data: { data: [{ device_id: 1, status: true, is_active: true, event_sequence: 0 }], next_cursor: null } });
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(store.statusFor(1)).toMatchObject({ status: false, is_active: false, source: 'realtime' });
  });

  it('coalesces overlapping recovery requests and applies the newest authoritative recovery snapshot', async () => {
    const snapshots = [];
    getDeviceStatusSnapshot.mockImplementation(() => new Promise((resolve) => snapshots.push(resolve)));

    const { subscribeDeviceStatus, DEVICE_STATUS_EVENT } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledOnce());
    fireResync('reconnect');

    const listenCall = echoMock.privateChannel.listen.mock.calls.find(([event]) => event === DEVICE_STATUS_EVENT);
    listenCall[1]({ device_id: 8, event_sequence: 9, status: false, is_active: false });

    snapshots[0]({ data: { data: [{ device_id: 8, status: true, is_active: true, event_sequence: 9 }], next_cursor: null } });
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledTimes(2));
    snapshots[1]({ data: { data: [{ device_id: 8, status: true, is_active: true, event_sequence: 9 }], next_cursor: null } });
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(store.statusFor(8)).toMatchObject({
      status: true,
      is_active: true,
      event_sequence: 9,
      source: 'recovery'
    });
  });

  it('feeds duplicate/out-of-order events through the store sequence guard', async () => {
    const { subscribeDeviceStatus, DEVICE_STATUS_EVENT } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledOnce());
    await new Promise((resolve) => setTimeout(resolve, 0));

    const listenCall = echoMock.privateChannel.listen.mock.calls.find(([event]) => event === DEVICE_STATUS_EVENT);
    const deliver = listenCall[1];

    deliver({ device_id: 2, event_sequence: 12, status: true, is_active: true });
    deliver({ device_id: 2, event_sequence: 11, status: false, is_active: false });

    expect(store.statusFor(2)).toMatchObject({ status: true, event_sequence: 12 });
  });

  it('uses a reconnect recovery snapshot to repair a device event missed while disconnected', async () => {
    const { subscribeDeviceStatus } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledOnce());
    await new Promise((resolve) => setTimeout(resolve, 0));
    store.applyStatusEvent({ device_id: 4, event_sequence: 12, status: true, is_active: true });

    getDeviceStatusSnapshot.mockResolvedValueOnce({ data: { data: [{ device_id: 4, status: false, is_active: false, event_sequence: 13 }], next_cursor: null } });
    fireResync('reconnect');
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledTimes(2));
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(store.statusFor(4)).toMatchObject({
      status: false,
      is_active: false,
      event_sequence: 13,
      source: 'recovery'
    });
  });

  it('reads every cursor page before applying the authoritative recovery snapshot', async () => {
    getDeviceStatusSnapshot
      .mockResolvedValueOnce({ data: { data: [{ device_id: 1, status: true, is_active: true, event_sequence: 4 }], next_cursor: 1 } })
      .mockResolvedValueOnce({ data: { data: [{ device_id: 2, status: false, is_active: false, event_sequence: 7 }], next_cursor: null } });
    const { subscribeDeviceStatus } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledTimes(2));

    expect(getDeviceStatusSnapshot).toHaveBeenNthCalledWith(1, { per_page: 100 });
    expect(getDeviceStatusSnapshot).toHaveBeenNthCalledWith(2, { per_page: 100, cursor: 1 });
    expect(store.statusFor(1)).toMatchObject({ event_sequence: 4, source: 'recovery' });
    expect(store.statusFor(2)).toMatchObject({ event_sequence: 7, source: 'recovery' });
  });

  it('unsubscribes and clears the projection on auth loss', async () => {
    const { subscribeDeviceStatus } = await import('./useDeviceStatusRealtime');
    const { getChannelRefCount } = await import('./channelRegistry');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    const authStore = useAuthStore();
    authenticate(authStore);
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDeviceStatusSnapshot).toHaveBeenCalledOnce());
    store.applyStatusEvent({ device_id: 3, event_sequence: 1, status: true, is_active: true });
    expect(store.statusFor(3)).not.toBeNull();

    authStore.clearAuth();
    fireResync('auth');

    expect(getChannelRefCount('device-status', { privateChannel: true })).toBe(0);
    expect(store.statusFor(3)).toBeNull();
  });

  it('never starts a timer for the recovery snapshot', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'setTimeout'] });
    const { subscribeDeviceStatus } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    authenticate(useAuthStore());

    subscribeDeviceStatus();
    fireResync('reconnect');

    expect(vi.getTimerCount()).toBe(0);
    vi.useRealTimers();
  });
});
