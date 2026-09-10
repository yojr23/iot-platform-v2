import { createApp, nextTick, ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// S1: SensorList subscribes each rendered sensor through the shared useSensorRealtime
// composable (one instance per visible sensor, ref-counted channel underneath — see
// realtime/channelRegistry.js). Capture what each row subscribes with, keyed by sensor id,
// so tests can fire a live reading for a specific row deterministically.
const subscribeSensor = vi.fn();
const unsubscribeSensor = vi.fn();
const onReadingBySensor = new Map();
vi.mock('@/realtime/useSensorRealtime', () => ({
  useSensorRealtime: (idSource, onReading) => {
    const id = typeof idSource === 'function' ? idSource() : idSource;
    onReadingBySensor.set(id, onReading);
    return {
      isConnected: { value: false },
      error: { value: '' },
      subscribeSensor: (...args) => subscribeSensor(id, ...args),
      unsubscribeSensor: (...args) => unsubscribeSensor(id, ...args)
    };
  }
}));

const at = () => new Date().toISOString();
const mountedApps = [];

async function mountList(sensorsRef) {
  const { default: SensorList } = await import('./SensorList.vue');
  const { useSensorReadingsStore } = await import('@/stores/sensorReadings');
  const el = document.createElement('div');
  const app = createApp({
    components: { SensorList },
    setup() {
      return { sensorsRef };
    },
    template: '<SensorList :sensors="sensorsRef" />'
  });
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  return {
    el,
    store: useSensorReadingsStore(),
    unmount: () => {
      app.unmount();
      const index = mountedApps.indexOf(app);
      if (index >= 0) mountedApps.splice(index, 1);
    }
  };
}

describe('SensorList live-patched last reading (S1)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    onReadingBySensor.clear();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('subscribes each rendered sensor on mount', async () => {
    const sensorsRef = ref([{ id: 1, name: 'S1', unit: 'C' }, { id: 2, name: 'S2', unit: 'C' }]);
    const { unmount } = await mountList(sensorsRef);

    expect(subscribeSensor).toHaveBeenCalledWith(1);
    expect(subscribeSensor).toHaveBeenCalledWith(2);

    unmount();
  });

  it('patches a row last value + timestamp when a live reading arrives', async () => {
    const sensorsRef = ref([{ id: 1, name: 'S1', unit: 'C' }]);
    const { el, unmount } = await mountList(sensorsRef);

    onReadingBySensor.get(1)({ id: 9, sensor_id: 1, value: 42, reading_time: at() });
    await nextTick();

    expect(el.textContent).toContain('42');

    unmount();
  });

  it('unsubscribes a sensor removed from the visible list', async () => {
    const sensorsRef = ref([{ id: 1, name: 'S1' }, { id: 2, name: 'S2' }]);
    const { unmount } = await mountList(sensorsRef);

    sensorsRef.value = [{ id: 1, name: 'S1' }];
    await nextTick();

    expect(unsubscribeSensor).toHaveBeenCalledWith(2);
    expect(unsubscribeSensor).not.toHaveBeenCalledWith(1);

    unmount();
  });

  it('unsubscribes every sensor on unmount', async () => {
    const sensorsRef = ref([{ id: 1, name: 'S1' }, { id: 2, name: 'S2' }]);
    const { unmount } = await mountList(sensorsRef);

    unmount();

    expect(unsubscribeSensor).toHaveBeenCalledWith(1);
    expect(unsubscribeSensor).toHaveBeenCalledWith(2);
  });
});
