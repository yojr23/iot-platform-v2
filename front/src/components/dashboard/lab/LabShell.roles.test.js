import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useRouter, useRoute } from 'vue-router';

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ name: 'dashboard' }),
}));

const mountedApps = [];

async function mountShell({ authenticated = false, admin = false } = {}) {
  const { default: LabShell } = await import('./LabShell.vue');
  const el = document.createElement('div');
  const app = createApp(LabShell);
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.use(createPinia());
  app.mount(el);
  mountedApps.push(app);
  if (authenticated) {
    const { useAuthStore } = await import('@/stores/auth');
    const authStore = useAuthStore();
    authStore.token = 'test-token';
    authStore.user = { id: 1, name: 'Test', is_admin: admin };
  }
  await nextTick();
  return { el, unmount: () => { app.unmount(); mountedApps.splice(mountedApps.indexOf(app), 1); } };
}

beforeEach(() => {
  vi.clearAllMocks();
  setActivePinia(createPinia());
});

afterEach(() => {
  mountedApps.splice(0).forEach((a) => a.unmount());
});

describe('LabShell navigation roles', () => {
  it('shows Métricas to any authenticated user', async () => {
    const { el, unmount } = await mountShell({ authenticated: true, admin: false });
    expect(el.textContent).toContain('Métricas');
    unmount();
  });

  it('hides Configuración from standard users', async () => {
    const { el, unmount } = await mountShell({ authenticated: true, admin: false });
    expect(el.textContent).not.toContain('Configuración');
    unmount();
  });

  it('shows Configuración to administrators', async () => {
    const { el, unmount } = await mountShell({ authenticated: true, admin: true });
    expect(el.textContent).toContain('Configuración');
    unmount();
  });
});
