import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// D5 (sensor side): SensorsView must honor a ?device_id= route query by opening the
// create-sensor modal with that device preselected, for admins only.
let currentQuery = {};
vi.mock('vue-router', () => ({
  useRoute: () => ({ query: currentQuery })
}));

vi.mock('@/realtime/useSensorRealtime', () => ({
  useSensorRealtime: () => ({
    isConnected: { value: false },
    error: { value: '' },
    subscribeSensor: vi.fn(),
    unsubscribeSensor: vi.fn()
  })
}));

const getSensors = vi.fn(() => Promise.resolve({ data: [] }));
vi.mock('@/api/sensors', () => ({
  getSensors: (...args) => getSensors(...args),
  createSensor: vi.fn(),
  updateSensor: vi.fn(),
  deleteSensor: vi.fn(),
  exportSensorReadings: vi.fn()
}));

const getDevices = vi.fn(() => Promise.resolve({ data: [{ id: 5, name: 'Device 5', status: true, is_active: true }] }));
vi.mock('@/api/devices', () => ({
  getDevices: (...args) => getDevices(...args)
}));

vi.mock('@/api/catalogs', () => ({
  getSensorTypes: vi.fn(() => Promise.resolve({ data: [] }))
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

async function mountView() {
  const { default: SensorsView } = await import('./SensorsView.vue');
  const { useAuthStore } = await import('@/stores/auth');
  const el = document.createElement('div');
  const app = createApp(SensorsView);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  const authStore = useAuthStore();
  authStore.user = { id: 1, is_admin: true };
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.mount(el);
  mountedApps.push(app);
  await flush();
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

describe('SensorsView device_id preselect (D5)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('opens the create-sensor modal with the device preselected when ?device_id= is present for an admin', async () => {
    currentQuery = { device_id: '5' };
    const { el, unmount } = await mountView();

    const select = el.querySelector('#sensor_device_id');
    expect(select).not.toBeNull();
    expect(select.value).toBe('5');

    unmount();
  });

  it('does not open the modal when no device_id query param is present', async () => {
    const { el, unmount } = await mountView();

    expect(el.querySelector('#sensor_device_id')).toBeNull();

    unmount();
  });
});
