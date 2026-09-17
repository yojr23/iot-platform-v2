import { createApp, h, nextTick, ref } from 'vue';
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
      realtimeStatus: { value: { connected: false, mode: 'disconnected', error: '' } },
      subscribeSensor,
      unsubscribeSensor
    };
  },
  RECOVERY_WINDOW_MS: 5 * 60 * 1000
}));

// These tests exercise the view's live-tail/table projection, not Chart.js pixels.
// Keep the pixel/render contract in SensorReadingChart.test.js and avoid jsdom's
// unsupported canvas context warning here.
vi.mock('@/components/sensors/SensorReadingsChart.vue', () => ({
  default: { template: '<div data-testid="sensor-readings-chart-stub" />' }
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
  let reject;
  const promise = new Promise((res, rej) => { resolve = res; reject = rej; });
  // Unhandled-rejection safety net: a stale request's guarded catch() may return before ever
  // touching `.catch`-relevant state, but the promise itself is always awaited by the caller
  // (loadTelemetry/filterReadings), so this is only a no-op safety net, not a real suppression.
  promise.catch(() => {});
  return { promise, resolve, reject };
}

async function mountView({ permissions = ['sensor.view', 'sensor_reading.view'] } = {}) {
  const { default: SensorDetailView } = await import('./SensorDetailView.vue');
  const { useSensorReadingsStore } = await import('@/stores/sensorReadings');
  const { useAuthStore } = await import('@/stores/auth');
  const el = document.createElement('div');
  const app = createApp(SensorDetailView, { id: '7' });
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  const auth = useAuthStore();
  auth.user = { id: 1, permissions };
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  return {
    el,
    auth,
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

  it('keeps metadata available without requesting or subscribing to telemetry for a metadata-only user', async () => {
    const { el, unmount } = await mountView({ permissions: ['sensor.view'] });
    await flush();
    await nextTick();

    expect(getSensorLatestReadings).not.toHaveBeenCalled();
    expect(subscribeSensor).not.toHaveBeenCalled();
    expect(el.textContent).toContain('S7');
    expect(el.textContent).toContain('Telemetría no disponible');

    unmount();
  });

  it('keeps export available to a metadata user with the distinct export permission', async () => {
    const { el, unmount } = await mountView({ permissions: ['sensor.view', 'sensor_reading.export'] });
    await flush();
    await nextTick();

    expect(getSensorLatestReadings).not.toHaveBeenCalled();
    expect(subscribeSensor).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Exportar lecturas');

    unmount();
  });

  it('releases telemetry and clears its projection when telemetry access is revoked while mounted', async () => {
    const { auth, store, unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    store.mergeReading('7', { id: 12, value: 12, reading_time: at(12) });
    auth.user.permissions = ['sensor.view'];
    await nextTick();

    expect(unsubscribeSensor).toHaveBeenCalledTimes(1);
    expect(store.readingsFor('7')).toEqual([]);
    expect(subscribeSensor).toHaveBeenCalledTimes(1);

    unmount();
  });

  it('loads telemetry when sensor_reading.view is granted after mount', async () => {
    const { auth, el, store, unmount } = await mountView({ permissions: ['sensor.view'] });
    await flush();
    await nextTick();

    expect(getSensorLatestReadings).not.toHaveBeenCalled();
    expect(subscribeSensor).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Telemetría no disponible');

    auth.user.permissions = ['sensor.view', 'sensor_reading.view'];
    latestDeferred.resolve({ data: [{ id: 20, value: 20, reading_time: at(20) }] });
    await flush();
    await nextTick();

    expect(getSensorLatestReadings).toHaveBeenCalledTimes(1);
    expect(subscribeSensor).toHaveBeenCalledTimes(1);
    expect(store.readingsFor('7')).toHaveLength(1);
    expect(el.textContent).not.toContain('Telemetría no disponible');

    unmount();
  });

  it('shows error when user has no permissions at all', async () => {
    getSensor.mockRejectedValueOnce({ response: { status: 403, data: { message: 'Forbidden' } } });
    const { el, unmount } = await mountView({ permissions: [] });
    await flush();
    await nextTick();

    expect(el.textContent).toContain('Forbidden');
    expect(getSensorLatestReadings).not.toHaveBeenCalled();
    expect(subscribeSensor).not.toHaveBeenCalled();

    unmount();
  });

  it('revokes export button when sensor_reading.export is removed', async () => {
    const { auth, el, unmount } = await mountView({ permissions: ['sensor.view', 'sensor_reading.export'] });
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    expect(el.textContent).toContain('Exportar lecturas');

    auth.user.permissions = ['sensor.view'];
    await nextTick();

    expect(el.textContent).not.toContain('Exportar lecturas');

    unmount();
  });

  it('sensor.view=yes + sensor_reading.view=no: no latest-readings call, no private subscription, metadata usable, telemetry unavailable', async () => {
    const { el, unmount } = await mountView({ permissions: ['sensor.view'] });
    await flush();
    await nextTick();

    expect(getSensorLatestReadings).not.toHaveBeenCalled();
    expect(subscribeSensor).not.toHaveBeenCalled();
    expect(el.textContent).toContain('S7');
    expect(el.textContent).toContain('Telemetría no disponible');
    expect(el.querySelector('form')).toBeNull();

    unmount();
  });

  it('telemetry permission revoked then re-granted: clears projection on revoke, then resubscribes and reloads telemetry on re-grant (Option A auto re-acquire)', async () => {
    const { auth, store, unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    store.mergeReading('7', { id: 30, value: 30, reading_time: at(30) });
    expect(store.readingsFor('7')).toHaveLength(1);
    expect(subscribeSensor).toHaveBeenCalledTimes(1);

    auth.user.permissions = ['sensor.view'];
    await nextTick();

    expect(unsubscribeSensor).toHaveBeenCalledTimes(1);
    expect(store.readingsFor('7')).toEqual([]);
    expect(subscribeSensor).toHaveBeenCalledTimes(1);

    // Re-grant after a prior revoke: Option A resubscribes AND reloads telemetry instead of
    // leaving the view stuck on an empty projection until a manual reload.
    latestDeferred = deferred();
    auth.user.permissions = ['sensor.view', 'sensor_reading.view'];
    await nextTick();
    latestDeferred.resolve({ data: [{ id: 31, value: 31, reading_time: at(31) }] });
    await flush();
    await nextTick();

    expect(subscribeSensor).toHaveBeenCalledTimes(2);
    expect(getSensorLatestReadings).toHaveBeenCalledTimes(2);
    expect(store.readingsFor('7')).toHaveLength(1);

    unmount();
  });

  it('login while sensor page is mounted: subscribes to telemetry if permission present', async () => {
    const { auth, el, unmount } = await mountView({ permissions: [] });
    getSensor.mockRejectedValueOnce({ response: { status: 403, data: { message: 'Forbidden' } } });
    await flush();
    await nextTick();

    expect(subscribeSensor).not.toHaveBeenCalled();

    getSensor.mockResolvedValueOnce({ data: { id: 7, name: 'S7', unit: 'C' } });
    auth.user.permissions = ['sensor.view', 'sensor_reading.view'];
    await nextTick();
    await flush();
    await nextTick();

    expect(subscribeSensor).toHaveBeenCalledTimes(1);
    expect(el.textContent).toContain('S7');

    unmount();
  });

  it('load() forwards the metadata AbortController signal to getSensor (Task 2)', async () => {
    const { unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    expect(getSensor).toHaveBeenCalledWith('7', expect.objectContaining({ signal: expect.any(AbortSignal) }));

    unmount();
  });

  it('logout while sensor page is mounted: clears projection and releases channel', async () => {
    const { auth, store, unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();

    store.mergeReading('7', { id: 40, value: 40, reading_time: at(40) });
    expect(store.readingsFor('7')).toHaveLength(1);

    auth.user = null;
    await nextTick();

    expect(unsubscribeSensor).toHaveBeenCalledTimes(1);
    expect(store.readingsFor('7')).toEqual([]);

    unmount();
  });
});

// mountView() above mounts SensorDetailView with a static `id` prop, which is enough for the
// permission-toggle regressions Task 3 already covers. The A->B navigation races need `props.id`
// to actually change post-mount (matching real router `:id` reuse), so this wraps the view in a
// tiny reactive-id root component instead of duplicating the whole mount/teardown harness.
async function mountViewNav({ initialId = '7', permissions = ['sensor.view', 'sensor_reading.view'] } = {}) {
  const { default: SensorDetailView } = await import('./SensorDetailView.vue');
  const { useSensorReadingsStore } = await import('@/stores/sensorReadings');
  const { useAuthStore } = await import('@/stores/auth');
  const el = document.createElement('div');
  const idRef = ref(initialId);
  const Root = { render: () => h(SensorDetailView, { id: idRef.value }) };
  const app = createApp(Root);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  const auth = useAuthStore();
  auth.user = { id: 1, permissions };
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  return {
    el,
    auth,
    store: useSensorReadingsStore(),
    setId: async (newId) => {
      idRef.value = newId;
      await nextTick();
    },
    unmount: () => {
      app.unmount();
      const index = mountedApps.indexOf(app);
      if (index >= 0) mountedApps.splice(index, 1);
    }
  };
}

describe('SensorDetailView: A->B navigation races (Task 3)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    capturedOnReading = null;
    latestDeferred = deferred();
    setActivePinia(createPinia());
    getSensor.mockImplementation((id) => Promise.resolve({ data: { id: Number(id), name: `S${id}`, unit: 'C' } }));
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('1. delayed A latest-readings resolves after B is active: A readings never land, B store holds only B data', async () => {
    const deferredA = deferred();
    const deferredB = deferred();
    getSensorLatestReadings
      .mockImplementationOnce(() => deferredA.promise)
      .mockImplementationOnce(() => deferredB.promise);

    const { store, setId, unmount } = await mountViewNav({ initialId: '7' });
    await setId('8');

    // A's request resolves late, after B is the active sensor.
    deferredA.resolve({ data: [{ id: 100, value: 100, reading_time: at(1) }] });
    await flush();
    await nextTick();

    expect(store.readingsFor('7')).toEqual([]);
    expect(store.readingsFor('8')).toEqual([]);

    deferredB.resolve({ data: [{ id: 200, value: 200, reading_time: at(2) }] });
    await flush();
    await nextTick();

    expect(store.readingsFor('8')).toHaveLength(1);
    expect(store.readingsFor('8')[0].id).toBe(200);
    expect(store.readingsFor('7')).toEqual([]);

    unmount();
  });

  it('2. delayed A latest-readings ERROR resolves after B is active: B error stays empty', async () => {
    const deferredA = deferred();
    getSensorLatestReadings
      .mockImplementationOnce(() => deferredA.promise)
      .mockImplementationOnce(() => Promise.resolve({ data: [{ id: 300, value: 300, reading_time: at(3) }] }));

    const { el, store, setId, unmount } = await mountViewNav({ initialId: '7' });
    await setId('8');

    deferredA.reject({ name: 'Error', message: 'A telemetry failed' });
    await flush();
    await nextTick();

    expect(el.textContent).not.toContain('No se pudieron cargar las lecturas del sensor');
    expect(store.readingsFor('8')).toHaveLength(1);

    unmount();
  });

  it('3. stale A filterReadings finally() after B is active does not flip B\'s filtering state', async () => {
    const filterDeferredA = deferred();
    const filterDeferredB = deferred();
    getSensorLatestReadings.mockImplementation(() => Promise.resolve({ data: [] }));
    getSensorReadings
      .mockImplementationOnce(() => filterDeferredA.promise)
      .mockImplementationOnce(() => filterDeferredB.promise);

    const { el, setId, unmount } = await mountViewNav({ initialId: '7' });
    await flush();
    await nextTick();

    // Start a historical filter for A and leave it pending.
    el.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await nextTick();

    // Navigate to B before A's filter resolves.
    await setId('8');
    await flush();
    await nextTick();

    // Start B's own filter and leave it pending too.
    el.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await nextTick();

    const filterButton = el.querySelector('button[type="submit"]');
    expect(filterButton.disabled).toBe(true);

    // A's stale filter resolves late; its finally() must not flip B's filtering flag off while
    // B's own filter is still in flight.
    filterDeferredA.resolve({ data: [{ id: 700, value: 700, reading_time: at(7) }] });
    await flush();
    await nextTick();

    expect(filterButton.disabled).toBe(true);
    expect(el.textContent).not.toContain('700');

    filterDeferredB.resolve({ data: [{ id: 800, value: 800, reading_time: at(8) }] });
    await flush();
    await nextTick();

    expect(filterButton.disabled).toBe(false);
    expect(el.textContent).toContain('800');

    unmount();
  });

  it('4. filtered-history request for A resolves after navigation to B: does not pollute B filteredReadings', async () => {
    const filterDeferredA = deferred();
    getSensorLatestReadings.mockImplementation(() => Promise.resolve({ data: [] }));
    getSensorReadings.mockImplementationOnce(() => filterDeferredA.promise);

    const { el, store, setId, unmount } = await mountViewNav({ initialId: '7' });
    await flush();
    await nextTick();

    el.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await nextTick();

    await setId('8');
    await flush();
    await nextTick();

    filterDeferredA.resolve({ data: [{ id: 900, value: 900, reading_time: at(9) }] });
    await flush();
    await nextTick();

    expect(el.textContent).not.toContain('900');

    // B's default (unfiltered) view still reads the shared live tail correctly.
    store.mergeReading('8', { id: 950, value: 950, reading_time: at(10) });
    await nextTick();
    expect(el.textContent).toContain('950');

    unmount();
  });

  it('5. a reading arriving on B during the snapshot/subscription transition survives (subscribe-before-snapshot)', async () => {
    const deferredA = deferred();
    const deferredB = deferred();
    getSensorLatestReadings
      .mockImplementationOnce(() => deferredA.promise)
      .mockImplementationOnce(() => deferredB.promise);

    const { store, setId, unmount } = await mountViewNav({ initialId: '7' });
    deferredA.resolve({ data: [] });
    await flush();
    await nextTick();

    expect(subscribeSensor).toHaveBeenCalledTimes(1);

    await setId('8');
    // subscribeSensor() for B must already have fired (Bug 2 reorder), even though B's REST
    // snapshot (deferredB) is still pending.
    expect(subscribeSensor).toHaveBeenCalledTimes(2);

    // A live event lands on B's channel while the snapshot request is still in flight.
    capturedOnReading({ id: 600, sensor_id: 8, value: 600, reading_time: at(6) });

    deferredB.resolve({ data: [{ id: 601, value: 601, reading_time: at(6) }] });
    await flush();
    await nextTick();

    const ids = store.readingsFor('8').map((reading) => reading.id);
    expect(ids).toContain(600);
    expect(ids).toContain(601);

    unmount();
  });
});

describe('SensorDetailView: A->B lifecycle regressions (Task 3)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    capturedOnReading = null;
    latestDeferred = deferred();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('permission revoked while the initial latest-readings request is in flight: late result is not merged', async () => {
    const { auth, store, unmount } = await mountView();
    // latestDeferred is still pending (not resolved) when telemetry is revoked.
    auth.user.permissions = ['sensor.view'];
    await nextTick();

    latestDeferred.resolve({ data: [{ id: 1, value: 1, reading_time: at(1) }] });
    await flush();
    await nextTick();

    expect(store.readingsFor('7')).toEqual([]);

    unmount();
  });

  it('revoke -> grant -> revoke -> grant: ends in a single-subscription live state, each re-grant issues exactly one new subscribe and one new reading request', async () => {
    const { auth, store, unmount } = await mountView();
    latestDeferred.resolve({ data: [] });
    await flush();
    await nextTick();
    expect(subscribeSensor).toHaveBeenCalledTimes(1);
    expect(getSensorLatestReadings).toHaveBeenCalledTimes(1);

    // revoke #1
    auth.user.permissions = ['sensor.view'];
    await nextTick();
    expect(unsubscribeSensor).toHaveBeenCalledTimes(1);
    expect(store.readingsFor('7')).toEqual([]);

    // grant #1 (Option A re-acquire)
    latestDeferred = deferred();
    auth.user.permissions = ['sensor.view', 'sensor_reading.view'];
    await nextTick();
    expect(subscribeSensor).toHaveBeenCalledTimes(2);
    expect(getSensorLatestReadings).toHaveBeenCalledTimes(2);
    latestDeferred.resolve({ data: [{ id: 10, value: 10, reading_time: at(10) }] });
    await flush();
    await nextTick();
    expect(store.readingsFor('7')).toHaveLength(1);

    // revoke #2
    auth.user.permissions = ['sensor.view'];
    await nextTick();
    expect(unsubscribeSensor).toHaveBeenCalledTimes(2);
    expect(store.readingsFor('7')).toEqual([]);

    // grant #2
    latestDeferred = deferred();
    auth.user.permissions = ['sensor.view', 'sensor_reading.view'];
    await nextTick();
    expect(subscribeSensor).toHaveBeenCalledTimes(3);
    expect(getSensorLatestReadings).toHaveBeenCalledTimes(3);
    latestDeferred.resolve({ data: [{ id: 20, value: 20, reading_time: at(20) }] });
    await flush();
    await nextTick();

    // Final state: exactly one active subscription (subscribe count exceeds unsubscribe count
    // by exactly 1) and the projection reflects only the final grant's data.
    expect(subscribeSensor).toHaveBeenCalledTimes(3);
    expect(unsubscribeSensor).toHaveBeenCalledTimes(2);
    expect(store.readingsFor('7')).toHaveLength(1);
    expect(store.readingsFor('7')[0].id).toBe(20);

    unmount();
  });

  it('unmount aborts the in-flight metadata request and releases the channel', async () => {
    let capturedSignal;
    getSensor.mockImplementationOnce((id, opts) => {
      capturedSignal = opts?.signal;
      return new Promise(() => {}); // never resolves within this test
    });

    const { unmount } = await mountView();
    await nextTick();

    expect(capturedSignal).toBeInstanceOf(AbortSignal);
    expect(capturedSignal.aborted).toBe(false);

    unmount();

    expect(capturedSignal.aborted).toBe(true);
    expect(unsubscribeSensor).toHaveBeenCalledTimes(1);
  });

  it('a canceled metadata request (aborted by rapid navigation) does not show a user-facing error', async () => {
    getSensor.mockRejectedValueOnce({ name: 'CanceledError', code: 'ERR_CANCELED', message: 'canceled' });
    const { el, unmount } = await mountView();
    await flush();
    await nextTick();

    expect(el.textContent).not.toContain('No se pudo cargar el sensor');

    unmount();
  });

  it('telemetry-denied view never calls latest-readings, readings, or export APIs and never subscribes', async () => {
    const { unmount } = await mountView({ permissions: ['sensor.view'] });
    await flush();
    await nextTick();

    expect(getSensorLatestReadings).not.toHaveBeenCalled();
    expect(getSensorReadings).not.toHaveBeenCalled();
    expect(exportSensorReadings).not.toHaveBeenCalled();
    expect(subscribeSensor).not.toHaveBeenCalled();

    unmount();
  });
});
