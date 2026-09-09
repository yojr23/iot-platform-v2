import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const fetchUnresolved = vi.fn(() => Promise.resolve({ data: { data: [], meta: { total: 0 } } }));
const fetchActiveAlerts = vi.fn(() => Promise.resolve({ data: { alerts: [], count: 0 } }));

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => fetchActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: (...args) => fetchUnresolved(...args),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn(),
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountNavBar() {
  const { default: NavBar } = await import('./NavBar.vue');
  const el = document.createElement('div');
  const app = createApp(NavBar);
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.mount(el);
  await nextTick();
  await flush();
  return () => app.unmount();
}

describe('NavBar guest vs authenticated alert badge refresh', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
  });

  it('makes no alert API call for a guest (unauthenticated) mount', async () => {
    const unmount = await mountNavBar();

    expect(fetchUnresolved).not.toHaveBeenCalled();
    expect(fetchActiveAlerts).not.toHaveBeenCalled();

    unmount();
  });

  it('fetches unresolved alerts for an authenticated mount', async () => {
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User' };

    const unmount = await mountNavBar();

    expect(fetchUnresolved).toHaveBeenCalledTimes(1);
    expect(fetchActiveAlerts).not.toHaveBeenCalled();

    unmount();
  });
});
