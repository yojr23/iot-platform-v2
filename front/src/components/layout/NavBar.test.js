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

async function mountNavBar({ authenticated = false, admin = false } = {}) {
  const { default: NavBar } = await import('./NavBar.vue');
  const el = document.createElement('div');
  const app = createApp(NavBar);
  app.component('RouterLink', { template: '<a><slot /></a>' });
  if (authenticated) {
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test User', is_admin: admin };
  }
  app.mount(el);
  await nextTick();
  await flush();
  return { el, unmount: () => app.unmount() };
}

describe('NavBar guest vs authenticated alert badge refresh', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
  });

  it('makes no alert API call for a guest (unauthenticated) mount', async () => {
    const { el, unmount } = await mountNavBar();

    expect(fetchUnresolved).not.toHaveBeenCalled();
    expect(fetchActiveAlerts).not.toHaveBeenCalled();
    expect(el.textContent).not.toContain('Polling');
    expect(el.textContent).not.toContain('Sin conexion');

    unmount();
  });

  it('fetches unresolved alerts for an authenticated mount', async () => {
    const { el, unmount } = await mountNavBar({ authenticated: true });

    expect(fetchUnresolved).toHaveBeenCalledTimes(1);
    expect(fetchActiveAlerts).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Sin conexion');

    unmount();
  });

  it('keeps only metrics and settings in the administrator navigation', async () => {
    const { el, unmount } = await mountNavBar({ authenticated: true, admin: true });

    expect(el.textContent).toContain('Métricas');
    expect(el.textContent).toContain('Configuración');
    expect(el.textContent).not.toContain('Reglas');
    expect(el.textContent).not.toContain('Catalogos');
    expect(el.textContent).not.toContain('Usuarios');

    unmount();
  });

  it('shows metrics to standard users but hides configuration', async () => {
    const { el, unmount } = await mountNavBar({ authenticated: true, admin: false });

    expect(el.textContent).toContain('Métricas');
    expect(el.textContent).not.toContain('Configuración');

    unmount();
  });
});
