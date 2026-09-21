import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getUsers = vi.fn();
const getRoles = vi.fn();
const updateUserRole = vi.fn();

vi.mock('@/api/users', () => ({
  getUsers: (...args) => getUsers(...args),
  getRoles: (...args) => getRoles(...args),
  updateUserRole: (...args) => updateUserRole(...args)
}));

const roles = [
  { code: 'superadmin', name: 'Super Administrator', assignable: false },
  { code: 'admin', name: 'Administrator', assignable: true },
  { code: 'user', name: 'User', assignable: true }
];

const mountedApps = [];
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

function userWithRole(roleCode) {
  return {
    id: 2,
    name: 'Target User',
    email: 'target@example.test',
    role: roles.find((role) => role.code === roleCode),
    permissions: [],
    created_at: '2026-01-01T00:00:00Z'
  };
}

async function mountView({ actorRoleCode, permissions = [], targetRoleCode }) {
  const { default: UserRolesView } = await import('./UserRolesView.vue');
  const { useAuthStore } = await import('@/stores/auth');
  const el = document.createElement('div');
  const app = createApp(UserRolesView);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);

  const authStore = useAuthStore();
  authStore.user = {
    id: actorRoleCode === targetRoleCode ? 2 : 1,
    role: roles.find((role) => role.code === actorRoleCode),
    permissions
  };

  app.mount(el);
  mountedApps.push(app);
  await flush();
  await nextTick();

  return { el };
}

function gearButton(el) {
  return el.querySelector('.dropdown-toggle');
}

describe('UserRolesView role transitions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
    getRoles.mockResolvedValue({ data: { data: roles } });
    getUsers.mockImplementation(() => Promise.resolve({
      data: { data: [userWithRole(currentTargetRoleCode)] }
    }));
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  let currentTargetRoleCode = 'user';

  it('explains that a role controls activities through its associated permissions', async () => {
    currentTargetRoleCode = 'user';
    const { el } = await mountView({ actorRoleCode: 'superadmin', targetRoleCode: 'user' });

    expect(el.textContent).toContain('Las actividades del sistema se representan mediante permisos asociados a cada rol.');
  });

  it('shows only the administrator transition to a superadmin viewing a user', async () => {
    currentTargetRoleCode = 'user';
    const { el } = await mountView({ actorRoleCode: 'superadmin', targetRoleCode: 'user' });

    expect(el.textContent).toContain('Cambiar a Administrator');
    expect(el.textContent).not.toContain('Cambiar a User');
    expect(gearButton(el).disabled).toBe(false);
  });

  it('shows only the user transition to a superadmin viewing an administrator', async () => {
    currentTargetRoleCode = 'admin';
    const { el } = await mountView({ actorRoleCode: 'superadmin', targetRoleCode: 'admin' });

    expect(el.textContent).toContain('Cambiar a User');
    expect(el.textContent).not.toContain('Cambiar a Administrator');
  });

  it('disables role changes for a superadmin target', async () => {
    currentTargetRoleCode = 'superadmin';
    const { el } = await mountView({ actorRoleCode: 'superadmin', targetRoleCode: 'superadmin' });

    expect(gearButton(el).disabled).toBe(true);
    expect(el.textContent).not.toContain('Cambiar a');
  });

  it('disables role changes for an administrator viewing a user despite the assignment permission', async () => {
    currentTargetRoleCode = 'user';
    const { el } = await mountView({
      actorRoleCode: 'admin',
      permissions: ['user.role.assign'],
      targetRoleCode: 'user'
    });

    expect(gearButton(el).disabled).toBe(true);
    expect(el.textContent).not.toContain('Cambiar a');
  });
});
