import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getDevice = vi.fn(() => Promise.resolve({
  data: { id: 1, name: 'Device 1', status: true, is_active: true }
}));
// Gate 8.5: sensors come from the protected /devices/{id}/sensor-list endpoint.
const getDeviceSensors = vi.fn(() => Promise.resolve({ data: [] }));

vi.mock('@/api/devices', () => ({
  getDevice: (...args) => getDevice(...args),
  getDeviceSensors: (...args) => getDeviceSensors(...args)
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

async function mountDeviceDetailView() {
  const { default: DeviceDetailView } = await import('./DeviceDetailView.vue');
  const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
  const el = document.createElement('div');
  const app = createApp(DeviceDetailView, { id: '1' });
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.component('RouterLink', { template: '<a><slot /></a>', props: ['to'] });
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

describe('DeviceDetailView shared status projection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getDevice.mockResolvedValue({ data: { id: 1, name: 'Device 1', status: true, is_active: true } });
    getDeviceSensors.mockResolvedValue({ data: [] });
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
});
