import { createApp, nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/api/catalogs', () => ({
  getLabs: vi.fn(() => Promise.resolve({ data: [] })),
  createLab: vi.fn(), updateLab: vi.fn(), deleteLab: vi.fn(),
  getSensorTypes: vi.fn(), createSensorType: vi.fn(), updateSensorType: vi.fn(), deleteSensorType: vi.fn(),
  getDeviceTypes: vi.fn(), createDeviceType: vi.fn(), updateDeviceType: vi.fn(), deleteDeviceType: vi.fn()
}));

vi.mock('@/api/users', () => ({
  getUsers: vi.fn(() => Promise.resolve({ data: [] })),
  updateUserRole: vi.fn()
}));

vi.mock('@/api/profile', () => ({
  getProfile: vi.fn(() => Promise.resolve({ data: { name: 'Ana', email: 'ana@sinoa.test' } }))
}));

const apps = [];
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mount(component, props = {}) {
  const host = document.createElement('div');
  const app = createApp(component, props);
  app.mount(host);
  apps.push(app);
  await flush();
  await nextTick();
  return host;
}

describe('admin resource surfaces', () => {
  it('uses the shared SINOA resource shell and icon actions in catalog management', async () => {
    const { default: CatalogAdminView } = await import('./CatalogAdminView.vue');
    const host = await mount(CatalogAdminView, { type: 'labs' });

    expect(host.querySelector('.lab-resource-page')).not.toBeNull();
    expect(host.querySelector('.lab-resource-toolbar')).not.toBeNull();
    expect(host.querySelector('.lab-action svg')).not.toBeNull();
  });

  it('uses an icon action for user-role changes', async () => {
    const { default: UserRolesView } = await import('./UserRolesView.vue');
    const host = await mount(UserRolesView);

    expect(host.querySelector('.lab-resource-page')).not.toBeNull();
    expect(host.querySelector('.lab-action svg')).not.toBeNull();
  });

  it('shows profile identity through the shared resource shell', async () => {
    const { default: ProfileView } = await import('./ProfileView.vue');
    const host = await mount(ProfileView);

    expect(host.querySelector('.lab-resource-page')).not.toBeNull();
    expect(host.querySelector('.lab-profile-identity svg')).not.toBeNull();
  });
});
