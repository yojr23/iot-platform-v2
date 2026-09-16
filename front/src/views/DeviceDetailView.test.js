import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getDevice = vi.fn(() => Promise.resolve({
  data: { id: 1, name: 'Device 1', status: true, is_active: true }
}));
// Gate 8.5: sensors come from the protected /devices/{id}/sensor-list endpoint.
const getDeviceSensors = vi.fn(() => Promise.resolve({ data: [] }));
const rotateDeviceKey = vi.fn();

vi.mock('@/api/devices', () => ({
  getDevice: (...args) => getDevice(...args),
  getDeviceSensors: (...args) => getDeviceSensors(...args),
  rotateDeviceKey: (...args) => rotateDeviceKey(...args),
  updateDeviceStatus: vi.fn()
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

async function mountDeviceDetailView({ permissions = [] } = {}) {
  const { default: DeviceDetailView } = await import('./DeviceDetailView.vue');
  const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
  const { useAuthStore } = await import('@/stores/auth');
  const el = document.createElement('div');
  const app = createApp(DeviceDetailView, { id: '1' });
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.component('RouterLink', { template: '<a><slot /></a>', props: ['to'] });
  const authStore = useAuthStore();
  authStore.token = 'test-token';
  authStore.user = { id: 1, name: 'Admin', permissions };
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  await flush();
  await nextTick();
  return {
    el,
    pinia,
    deviceStatuses: useDeviceStatusesStore(),
    unmount: () => {
      app.unmount();
      const index = mountedApps.indexOf(app);
      if (index >= 0) mountedApps.splice(index, 1);
    }
  };
}

describe('DeviceDetailView shared status projection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getDevice.mockResolvedValue({ data: { id: 1, name: 'Device 1', status: true, is_active: true } });
    getDeviceSensors.mockResolvedValue({ data: [] });
    rotateDeviceKey.mockResolvedValue({ data: { api_key: 'rotated-device-secret' } });
    localStorage.clear();
    sessionStorage.clear();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('uses the protected sensor-list endpoint and seeds the shared projection on load', async () => {
    const { el, deviceStatuses, unmount } = await mountDeviceDetailView();

    expect(getDeviceSensors).toHaveBeenCalledWith('1');
    expect(deviceStatuses.statusFor(1)).toMatchObject({ status: true, is_active: true, source: 'snapshot' });
    expect(el.textContent).toContain('Activo');

    unmount();
  });

  it('reflects a realtime status event without an independent refresh', async () => {
    const { el, deviceStatuses, unmount } = await mountDeviceDetailView();

    deviceStatuses.applyStatusEvent({ device_id: 1, event_sequence: 1, status: false, is_active: false });
    await nextTick();

    expect(getDevice).toHaveBeenCalledTimes(1);
    expect(getDeviceSensors).toHaveBeenCalledTimes(1);
    expect(el.textContent).toContain('Inactivo');

    unmount();
  });

  it('shows device freshness alongside the operational status', async () => {
    const { el, deviceStatuses, unmount } = await mountDeviceDetailView();

    deviceStatuses.setRealtimeStatus({ enabled: true, connected: true, mode: 'recovering' });
    await nextTick();

    expect(el.textContent).toContain('Sincronizando');
    unmount();
  });

  it('rotates and displays a new one-time API key only for authorized users', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const { el, pinia, unmount } = await mountDeviceDetailView({ permissions: ['device.api_key.rotate'] });

    const rotateButton = [...el.querySelectorAll('button')].find((button) => button.textContent.includes('Rotar clave de API'));
    expect(rotateButton).toBeTruthy();
    rotateButton.dispatchEvent(new Event('click', { bubbles: true }));

    await vi.waitFor(() => expect(rotateDeviceKey).toHaveBeenCalledWith(1));
    await vi.waitFor(() => expect(el.querySelector('#one_time_device_api_key')?.value).toBe('rotated-device-secret'));
    expect(JSON.stringify(pinia.state.value)).not.toContain('rotated-device-secret');

    el.querySelector('button[aria-label="Cerrar"]').dispatchEvent(new Event('click', { bubbles: true }));
    await nextTick();
    expect(el.querySelector('#one_time_device_api_key')).toBeNull();

    unmount();
  });

  it('does not render the rotation control without device.api_key.rotate', async () => {
    const { el, unmount } = await mountDeviceDetailView();

    expect(el.textContent).not.toContain('Rotar clave de API');
    expect(rotateDeviceKey).not.toHaveBeenCalled();

    unmount();
  });
});
