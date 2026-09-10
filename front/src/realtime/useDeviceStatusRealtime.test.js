import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const connectionWatchers = new Set();
const resyncWatchers = new Set();
const getDevices = vi.fn();

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
  getDevices: (...args) => getDevices(...args),
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
    getDevices.mockReset();
    getDevices.mockResolvedValue({ data: [] });
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
    expect(getDevices).not.toHaveBeenCalled();
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
    getDevices.mockImplementation(() => new Promise((resolve) => {
      resolveSnapshot = resolve;
    }));

    const { subscribeDeviceStatus, DEVICE_STATUS_EVENT } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDevices).toHaveBeenCalledOnce());

    // The device metadata snapshot for device 1 is still "active" while a live event
    // already reports it went inactive — the race this buffer must survive.
    const listenCall = echoMock.privateChannel.listen.mock.calls.find(([event]) => event === DEVICE_STATUS_EVENT);
    const deliver = listenCall[1];
    deliver({ device_id: 1, event_sequence: 5, status: false, is_active: false });

    // Not lost, but not applied yet either (snapshot still in flight).
    expect(store.statusFor(1)).toBeNull();

    resolveSnapshot({ data: [{ id: 1, status: true, is_active: true }] });
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(store.statusFor(1)).toMatchObject({ status: false, is_active: false, source: 'realtime' });
  });

  it('coalesces overlapping recovery requests so an earlier response cannot overwrite the newer projection', async () => {
    const snapshots = [];
    getDevices.mockImplementation(() => new Promise((resolve) => snapshots.push(resolve)));

    const { subscribeDeviceStatus, DEVICE_STATUS_EVENT } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDevices).toHaveBeenCalledOnce());
    fireResync('reconnect');

    const listenCall = echoMock.privateChannel.listen.mock.calls.find(([event]) => event === DEVICE_STATUS_EVENT);
    listenCall[1]({ device_id: 8, event_sequence: 9, status: false, is_active: false });

    snapshots[0]({ data: [{ id: 8, status: true, is_active: true }] });
    await vi.waitFor(() => expect(getDevices).toHaveBeenCalledTimes(2));
    snapshots[1]({ data: [{ id: 8, status: true, is_active: true }] });
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(store.statusFor(8)).toMatchObject({
      status: false,
      is_active: false,
      event_sequence: 9,
      source: 'realtime'
    });
  });

  it('feeds duplicate/out-of-order events through the store sequence guard', async () => {
    const { subscribeDeviceStatus, DEVICE_STATUS_EVENT } = await import('./useDeviceStatusRealtime');
    const { useAuthStore } = await import('@/stores/auth');
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    authenticate(useAuthStore());
    const store = useDeviceStatusesStore();

    subscribeDeviceStatus();
    await vi.waitFor(() => expect(getDevices).toHaveBeenCalledOnce());

    const listenCall = echoMock.privateChannel.listen.mock.calls.find(([event]) => event === DEVICE_STATUS_EVENT);
    const deliver = listenCall[1];

    deliver({ device_id: 2, event_sequence: 12, status: true, is_active: true });
    deliver({ device_id: 2, event_sequence: 11, status: false, is_active: false });

    expect(store.statusFor(2)).toMatchObject({ status: true, event_sequence: 12 });
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
    await vi.waitFor(() => expect(getDevices).toHaveBeenCalledOnce());
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
