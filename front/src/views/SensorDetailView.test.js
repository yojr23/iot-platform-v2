import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Capture the onReading callback the view hands the realtime composable so tests can fire live
// events deterministically. The composable itself (channel lifecycle) is out of scope here.
let capturedOnReading = null;
const subscribeSensor = vi.fn();
const unsubscribeSensor = vi.fn();
vi.mock('@/realtime/useSensorRealtime', () => ({
  useSensorRealtime: (idSource, onReading) => {
    capturedOnReading = onReading;
    return {
      isConnected: { value: false },
      error: { value: '' },
      subscribeSensor,
      unsubscribeSensor
    };
  },
  RECOVERY_WINDOW_MS: 5 * 60 * 1000
}));

const getSensor = vi.fn(() => Promise.resolve({ data: { id: 7, name: 'S7', unit: 'C' } }));
let latestDeferred;
const getSensorLatestReadings = vi.fn(() => latestDeferred.promise);
const getSensorReadings = vi.fn(() => Promise.resolve({ data: [] }));
const exportSensorReadings = vi.fn();
vi.mock('@/api/sensors', () => ({
  getSensor: (...args) => getSensor(...args),
  getSensorLatestReadings: (...args) => getSensorLatestReadings(...args),
  getSensorReadings: (...args) => getSensorReadings(...args),
  exportSensorReadings: (...args) => exportSensorReadings(...args)
}));

const at = (second) => new Date(Date.UTC(2026, 0, 1, 0, 0, second)).toISOString();
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

function deferred() {
  let resolve;
  const promise = new Promise((res) => { resolve = res; });
  return { promise, resolve };
}

async function mountView() {
  const { default: SensorDetailView } = await import('./SensorDetailView.vue');
  const { useSensorReadingsStore } = await import('@/stores/sensorReadings');
  const el = document.createElement('div');
  const app = createApp(SensorDetailView, { id: '7' });
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

describe('SensorDetailView shared-tail live projection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    capturedOnReading = null;
    latestDeferred = deferred();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('subscribes before the snapshot, so a live event during load survives the merge exactly once', async () => {
    const { store, unmount } = await mountView();

    expect(subscribeSensor).toHaveBeenCalled();
    // Live event arrives while the latest-readings snapshot is still pending.
    capturedOnReading({ id: 101, sensor_id: 7, value: 101, reading_time: at(41) });
    // Snapshot 1..3 resolves (newest-first, as the API returns it).
    latestDeferred.resolve({ data: [
      { id: 3, value: 3, reading_time: at(3) },
      { id: 2, value: 2, reading_time: at(2) },
      { id: 1, value: 1, reading_time: at(1) }
    ] });
    await flush();
    await nextTick();

    const ids = store.readingsFor('7').map((reading) => reading.id);
    expect(ids.filter((id) => id === 101)).toHaveLength(1);
    expect(store.readingsFor('7')).toHaveLength(4);

    unmount();
  });

  it('default view reads the shared live tail (a merged event shows in the table)', async () => {
    const { el, store, unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    store.mergeReading('7', { id: 5, value: 42, reading_time: at(5) });
    await nextTick();

    expect(el.textContent).toContain('42');

    unmount();
  });

  it('a duplicate live event collapses instead of appending', async () => {
    const { store, unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    capturedOnReading({ id: 9, sensor_id: 7, value: 9, reading_time: at(9) });
    capturedOnReading({ id: 9, sensor_id: 7, value: 9, reading_time: at(9) });
    await nextTick();

    expect(store.readingsFor('7')).toHaveLength(1);

    unmount();
  });

  it('a filtered historical view is not mutated by an unrelated live event', async () => {
    getSensorReadings.mockResolvedValueOnce({ data: [{ id: 500, value: 500, reading_time: at(50) }] });
    const { el, store, unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    // Enter the explicit historical filter.
    el.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await flush();
    await nextTick();

    const rowsBefore = el.querySelectorAll('tbody tr').length;
    expect(el.textContent).toContain('500');

    // An unrelated live event lands in the shared tail but must not change the filtered view.
    capturedOnReading({ id: 999, sensor_id: 7, value: 999, reading_time: at(59) });
    await nextTick();

    expect(el.querySelectorAll('tbody tr').length).toBe(rowsBefore);
    expect(el.textContent).not.toContain('999');
    // The shared tail still received the event (it is just not what the filtered view renders).
    expect(store.readingsFor('7').some((reading) => reading.id === 999)).toBe(true);

    unmount();
  });
});
