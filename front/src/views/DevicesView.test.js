import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getDevices = vi.fn(() => Promise.resolve({
  data: {
    data: [
      { id: 1, name: 'Device 1', status: true, is_active: true, device_type: null, lab: null, sensors: [] }
    ]
  }
}));
const updateDeviceStatus = vi.fn(() => Promise.resolve({
  data: { message: 'ok', device: { id: 1, status: false, is_active: false } }
}));

vi.mock('@/api/devices', () => ({
  getDevices: (...args) => getDevices(...args),
  getDevice: vi.fn(),
  createDevice: vi.fn(),
  updateDevice: vi.fn(),
  deleteDevice: vi.fn(),
  updateDeviceStatus: (...args) => updateDeviceStatus(...args),
  getDeviceSensors: vi.fn()
}));

vi.mock('@/api/catalogs', () => ({
  getDeviceTypes: vi.fn(() => Promise.resolve({ data: [] })),
  getLabs: vi.fn(() => Promise.resolve({ data: [] }))
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

async function mountDevicesView() {
  const { default: DevicesView } = await import('./DevicesView.vue');
  const { useAuthStore } = await import('@/stores/auth');
  const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
  const el = document.createElement('div');
  const app = createApp(DevicesView);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.component('RouterLink', { template: '<a><slot /></a>', props: ['to'] });
  const authStore = useAuthStore();
  authStore.token = 'test-token';
  authStore.user = { id: 1, name: 'Admin', is_admin: true };
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  await flush();
  await nextTick();
  return {
    el,
    deviceStatuses: useDeviceStatusesStore(),
    unmount: () => {
      app.unmount();
      const index = mountedApps.indexOf(app);
      if (index >= 0) mountedApps.splice(index, 1);
    }
  };
}

describe('DevicesView shared status projection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getDevices.mockResolvedValue({
      data: {
        data: [
          { id: 1, name: 'Device 1', status: true, is_active: true, device_type: null, lab: null, sensors: [] }
        ]
      }
    });
    updateDeviceStatus.mockResolvedValue({
      data: { message: 'ok', device: { id: 1, status: false, is_active: false } }
    });
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('seeds the shared projection from the initial device list load', async () => {
    const { deviceStatuses, unmount } = await mountDevicesView();

    expect(deviceStatuses.statusFor(1)).toMatchObject({ status: true, is_active: true, source: 'snapshot' });

    unmount();
  });

  it('applies the toggle response into the projection without reloading the full list', async () => {
    const { el, deviceStatuses, unmount } = await mountDevicesView();

    expect(getDevices).toHaveBeenCalledTimes(1);
    expect(el.textContent).toContain('Activo');

    const toggleButton = [...el.querySelectorAll('button')].find((btn) => btn.textContent.trim() === 'Activo');
    toggleButton.dispatchEvent(new Event('click', { bubbles: true }));
    await flush();
    await nextTick();

    expect(updateDeviceStatus).toHaveBeenCalledTimes(1);
    // Gate 8.5: no full-list reload just to learn the new status.
    expect(getDevices).toHaveBeenCalledTimes(1);
    expect(deviceStatuses.statusFor(1)).toMatchObject({ status: false, is_active: false });
    expect(el.textContent).toContain('Inactivo');

    unmount();
  });

  it('reflects a realtime status event in the rendered list via the shared projection', async () => {
    const { el, deviceStatuses, unmount } = await mountDevicesView();

    deviceStatuses.applyStatusEvent({ device_id: 1, event_sequence: 1, status: false, is_active: false });
    await nextTick();

    expect(getDevices).toHaveBeenCalledTimes(1);
    expect(el.textContent).toContain('Inactivo');

    unmount();
  });
});
