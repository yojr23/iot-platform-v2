import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const subscribeAlerts = vi.fn();
const unsubscribeAlerts = vi.fn();
const getPublicConfig = vi.fn(() => Promise.resolve({ data: {} }));
const getActiveAlerts = vi.fn(() => Promise.resolve({ data: { alerts: [], count: 0 } }));

vi.mock('./NavBar.vue', () => ({ default: { template: '<div />' } }));
vi.mock('@/components/alerts/AlertToast.vue', () => ({ default: { template: '<div />' } }));

vi.mock('@/realtime/useAlertsRealtime', () => ({
  useAlertsRealtime: () => ({ subscribeAlerts, unsubscribeAlerts }),
}));

vi.mock('@/utils/sound', () => ({
  playAlertSound: vi.fn(),
  unlockAlertSound: vi.fn(),
}));

vi.mock('@/api/config', () => ({ getPublicConfig: (...args) => getPublicConfig(...args) }));
vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => getActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn(),
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountAppLayout() {
  const { default: AppLayout } = await import('./AppLayout.vue');
  const el = document.createElement('div');
  const app = createApp(AppLayout);
  app.mount(el);
  await nextTick();
  await flush();
  await flush();
  return () => app.unmount();
}

describe('AppLayout guest vs authenticated alert initialization', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    setActivePinia(createPinia());
  });

  it('does not initialize the alert subsystem for a guest (unauthenticated) mount', async () => {
    const unmount = await mountAppLayout();

    expect(getPublicConfig).not.toHaveBeenCalled();
    expect(getActiveAlerts).not.toHaveBeenCalled();
    expect(subscribeAlerts).not.toHaveBeenCalled();
    expect(vi.getTimerCount()).toBe(0);

    unmount();
    vi.useRealTimers();
  });

  it('performs the existing alert initialization for an authenticated mount', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };

    const unmount = await mountAppLayout();

    expect(getPublicConfig).toHaveBeenCalledTimes(1);
    expect(getActiveAlerts).toHaveBeenCalledTimes(1);
    expect(subscribeAlerts).toHaveBeenCalledTimes(1);
    expect(vi.getTimerCount()).toBe(1);

    unmount();
    vi.useRealTimers();
  });
});
