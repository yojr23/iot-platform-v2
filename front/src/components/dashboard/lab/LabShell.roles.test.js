import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useRouter, useRoute } from 'vue-router';

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ name: 'dashboard' }),
}));

const mountedApps = [];

async function mountShell({ authenticated = false, permissions = [], roleCode = 'user' } = {}) {
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
    const levelMap = { superadmin: 3, admin: 2, user: 1 };
    authStore.user = {
      id: 1,
      name: 'Test',
      role: { code: roleCode, level: levelMap[roleCode] ?? 1 },
      permissions,
    };
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
    const { el, unmount } = await mountShell({ authenticated: true });
    expect(el.textContent).toContain('Métricas');
    unmount();
  });

  it('hides Configuración from standard users', async () => {
    const { el, unmount } = await mountShell({ authenticated: true });
    expect(el.textContent).not.toContain('Configuración');
    unmount();
  });

  it('shows Configuración to administrators', async () => {
    const { el, unmount } = await mountShell({ authenticated: true, roleCode: 'superadmin' });
    expect(el.textContent).toContain('Configuración');
    unmount();
  });
});

describe('LabShell permission-gated nav (W1-W4)', () => {
  it('hides Dispositivos when device.view is missing', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      permissions: ['sensor.view', 'alert.view'],
    });
    expect(el.textContent).not.toContain('Dispositivos');
    unmount();
  });

  it('shows Dispositivos when device.view is present', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      permissions: ['device.view'],
    });
    expect(el.textContent).toContain('Dispositivos');
    unmount();
  });

  it('hides Sensores when sensor.view is missing', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      permissions: ['device.view', 'alert.view'],
    });
    expect(el.textContent).not.toContain('Sensores');
    unmount();
  });

  it('shows Sensores when sensor.view is present', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      permissions: ['sensor.view'],
    });
    expect(el.textContent).toContain('Sensores');
    unmount();
  });

  it('hides Alertas nav link when alert.view is missing', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      permissions: ['device.view', 'sensor.view'],
    });
    const nav = el.querySelector('nav[aria-label="Principal"]');
    const navText = nav ? nav.textContent : '';
    expect(navText).not.toContain('Alertas');
    expect(navText).toContain('Dispositivos');
    expect(navText).toContain('Sensores');
    unmount();
  });

  it('shows Alertas when alert.view is present', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      permissions: ['alert.view'],
    });
    expect(el.textContent).toContain('Alertas');
    unmount();
  });

  it('hides all resource nav links for user with no permissions', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      permissions: [],
    });
    const nav = el.querySelector('nav[aria-label="Principal"]');
    const navText = nav ? nav.textContent : '';
    expect(navText).not.toContain('Dispositivos');
    expect(navText).not.toContain('Sensores');
    expect(navText).not.toContain('Alertas');
    unmount();
  });

  it('shows all resource links for superadmin (bypass)', async () => {
    const { el, unmount } = await mountShell({
      authenticated: true,
      roleCode: 'superadmin',
      permissions: [],
    });
    expect(el.textContent).toContain('Dispositivos');
    expect(el.textContent).toContain('Sensores');
    expect(el.textContent).toContain('Alertas');
    unmount();
  });
});
