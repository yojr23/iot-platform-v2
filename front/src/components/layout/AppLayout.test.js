import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const subscribeAlerts = vi.fn(() => true);
const unsubscribeAlerts = vi.fn();
const getRuntimeConfig = vi.fn(() => Promise.resolve({ data: {} }));
const getPublicConfig = vi.fn(() => Promise.resolve({ data: {} }));
const getActiveAlerts = vi.fn(() => Promise.resolve({ data: { alerts: [], count: 0 } }));

vi.mock('./NavBar.vue', () => ({ default: { template: '<div />' } }));
vi.mock('@/components/alerts/AlertToast.vue', () => ({ default: { template: '<div data-testid="alert-toast-host" />' } }));

vi.mock('@/realtime/useAlertsRealtime', () => ({
  useAlertsRealtime: () => ({ subscribeAlerts, unsubscribeAlerts }),
}));

vi.mock('@/utils/sound', () => ({
  playAlertSound: vi.fn(),
  unlockAlertSound: vi.fn(),
}));

vi.mock('@/api/config', () => ({
  getRuntimeConfig: (...args) => getRuntimeConfig(...args),
  getPublicConfig: (...args) => getPublicConfig(...args),
}));
vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => getActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn(),
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((promiseResolve, promiseReject) => {
    resolve = promiseResolve;
    reject = promiseReject;
  });
  return { promise, resolve, reject };
}

async function mountAppLayout() {
  const { default: AppLayout } = await import('./AppLayout.vue');
  const el = document.createElement('div');
  const app = createApp(AppLayout);
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  await flush();
  await flush();
  return {
    el,
    unmount: () => {
      app.unmount();
      const appIndex = mountedApps.indexOf(app);
      if (appIndex >= 0) {
        mountedApps.splice(appIndex, 1);
      }
    },
  };
}

describe('AppLayout guest vs authenticated alert initialization', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
    vi.useRealTimers();
  });

  it('does not initialize the alert subsystem for a guest (unauthenticated) mount', async () => {
    const { el, unmount } = await mountAppLayout();

    expect(el.querySelector('[data-testid="alert-toast-host"]')).toBeNull();
    expect(getRuntimeConfig).not.toHaveBeenCalled();
    expect(getPublicConfig).not.toHaveBeenCalled();
    expect(getActiveAlerts).not.toHaveBeenCalled();
    expect(subscribeAlerts).not.toHaveBeenCalled();
    expect(vi.getTimerCount()).toBe(0);

    unmount();
  });

  it('performs the existing alert initialization for an authenticated mount', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };

    const { el, unmount } = await mountAppLayout();

    expect(el.querySelector('[data-testid="alert-toast-host"]')).not.toBeNull();
    expect(getRuntimeConfig).toHaveBeenCalledTimes(1);
    expect(getPublicConfig).not.toHaveBeenCalled();
    expect(getActiveAlerts).toHaveBeenCalledTimes(1);
    expect(subscribeAlerts).toHaveBeenCalledTimes(1);
    expect(vi.getTimerCount()).toBe(0);

    unmount();
  });

  it('starts and stops global alerts as authentication changes after mount', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const { useAlertsStore } = await import('@/stores/alerts');
    const authStore = useAuthStore();
    const alertsStore = useAlertsStore();

    const { el, unmount } = await mountAppLayout();
    expect(vi.getTimerCount()).toBe(0);

    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };
    await nextTick();
    await flush();
    await flush();

    expect(getRuntimeConfig).toHaveBeenCalledTimes(1);
    expect(getActiveAlerts).toHaveBeenCalledTimes(1);
    expect(subscribeAlerts).toHaveBeenCalledTimes(1);
    expect(el.querySelector('[data-testid="alert-toast-host"]')).not.toBeNull();
    expect(vi.getTimerCount()).toBe(0);

    alertsStore.items = [{ id: 10 }];
    alertsStore.activeAlerts = [{ id: 10 }];
    alertsStore.unresolvedCount = 1;
    alertsStore.latestAlert = { id: 10 };
    alertsStore.setRealtimeStatus({
      enabled: true,
      connected: true,
      mode: 'live',
      channel: 'alerts',
      error: null,
    });

    authStore.clearAuth();
    await nextTick();
    await flush();

    expect(unsubscribeAlerts).toHaveBeenCalledTimes(1);
    expect(vi.getTimerCount()).toBe(0);
    expect(alertsStore.items).toEqual([]);
    expect(alertsStore.activeAlerts).toEqual([]);
    expect(alertsStore.unresolvedCount).toBe(0);
    expect(alertsStore.latestAlert).toBeNull();
    expect(alertsStore.realtimeStatus).toMatchObject({
      enabled: false,
      connected: false,
      mode: 'disconnected',
      channel: null,
    });
    expect(el.querySelector('[data-testid="alert-toast-host"]')).toBeNull();

    unmount();
  });

  it('does not start the alert polling timer when realtime subscription is unavailable', async () => {
    subscribeAlerts.mockReturnValueOnce(false);
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };

    const { unmount } = await mountAppLayout();

    expect(getRuntimeConfig).toHaveBeenCalledTimes(1);
    expect(getActiveAlerts).toHaveBeenCalledTimes(1);
    expect(subscribeAlerts).toHaveBeenCalledTimes(1);
    expect(unsubscribeAlerts).not.toHaveBeenCalled();
    expect(vi.getTimerCount()).toBe(0);

    unmount();
  });

  it('ignores stale alert startup work after logout and login race', async () => {
    const firstRuntimeConfig = deferred();
    const secondRuntimeConfig = deferred();
    getRuntimeConfig
      .mockReturnValueOnce(firstRuntimeConfig.promise)
      .mockReturnValueOnce(secondRuntimeConfig.promise);
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();

    const { unmount } = await mountAppLayout();

    authStore.token = 'first-token';
    authStore.user = { id: 1, name: 'First User' };
    await nextTick();
    await flush();

    authStore.clearAuth();
    await nextTick();
    await flush();

    authStore.token = 'second-token';
    authStore.user = { id: 2, name: 'Second User' };
    await nextTick();
    await flush();

    firstRuntimeConfig.resolve({ data: {} });
    await flush();
    await flush();

    expect(getRuntimeConfig).toHaveBeenCalledTimes(2);
    expect(getActiveAlerts).not.toHaveBeenCalled();
    expect(subscribeAlerts).not.toHaveBeenCalled();
    expect(vi.getTimerCount()).toBe(0);

    secondRuntimeConfig.resolve({ data: {} });
    await flush();
    await flush();

    expect(getActiveAlerts).toHaveBeenCalledTimes(1);
    expect(subscribeAlerts).toHaveBeenCalledTimes(1);
    expect(vi.getTimerCount()).toBe(0);

    unmount();
  });
});
