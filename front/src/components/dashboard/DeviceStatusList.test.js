import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getDevices = vi.fn();
vi.mock('@/api/devices', () => ({
  getDevices: (...args) => getDevices(...args)
}));

const mountedApps = [];

async function mountDeviceStatusList(devices) {
  const { default: DeviceStatusList } = await import('./DeviceStatusList.vue');
  const el = document.createElement('div');
  const app = createApp(DeviceStatusList, { devices });
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.component('RouterLink', { template: '<a><slot /></a>', props: ['to'] });
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  return {
    el,
    unmount: () => {
      app.unmount();
      const index = mountedApps.indexOf(app);
      if (index >= 0) mountedApps.splice(index, 1);
    }
  };
}

describe('DeviceStatusList presentation-only projection overlay', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('never fetches devices itself; renders whatever metadata the parent passes', async () => {
    const { el, unmount } = await mountDeviceStatusList([
      { id: 1, name: 'Device 1', status: true, device_type: null, sensors: [] }
    ]);

    expect(getDevices).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Device 1');
    expect(el.textContent).toContain('Activo');

    unmount();
  });

  it('overlays a shared realtime status update on top of the passed metadata', async () => {
    const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
    const { el, unmount } = await mountDeviceStatusList([
      { id: 1, name: 'Device 1', status: true, device_type: null, sensors: [] }
    ]);

    useDeviceStatusesStore().applyStatusEvent({ device_id: 1, event_sequence: 1, status: false, is_active: false });
    await nextTick();

    expect(getDevices).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Inactivo');

    unmount();
  });
});
