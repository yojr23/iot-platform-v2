import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

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

const getSensors = vi.fn(() => Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0, per_page: 50 } }));
vi.mock('@/api/sensors', () => ({
  getSensors: (...args) => getSensors(...args),
  createSensor: vi.fn(),
  updateSensor: vi.fn(),
  deleteSensor: vi.fn(),
  exportSensorReadings: vi.fn()
}));

const getDevices = vi.fn(() => Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0, per_page: 20 } }));
vi.mock('@/api/devices', () => ({
  getDevices: (...args) => getDevices(...args)
}));

vi.mock('@/api/catalogs', () => ({
  getSensorTypes: vi.fn(() => Promise.resolve({ data: [] }))
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

async function mountView({ permissions = ['sensor.create'] } = {}) {
  const { default: SensorsView } = await import('./SensorsView.vue');
  const { useAuthStore } = await import('@/stores/auth');
  const el = document.createElement('div');
  const app = createApp(SensorsView);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  const authStore = useAuthStore();
  authStore.user = { id: 1, role: { code: 'superadmin', level: 3 }, permissions };
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

function makeSensorResponse(data, meta = {}) {
  return { data: { data, current_page: meta.page ?? 1, last_page: meta.lastPage ?? 1, total: meta.total ?? data.length, per_page: meta.perPage ?? 50 } };
}

describe('SensorsView device_id preselect (D5)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
    getDevices.mockResolvedValue({ data: { data: [{ id: 5, name: 'Device 5', status: true, is_active: true }], current_page: 1, last_page: 1, total: 1, per_page: 20 } });
    getSensors.mockResolvedValue(makeSensorResponse([]));
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('opens the create-sensor modal with the device preselected when ?device_id= is present for an admin', async () => {
    currentQuery = { device_id: '5' };
    const { el, unmount } = await mountView();

    // Wait for form catalogs to load
    await flush();
    await nextTick();

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

describe('SensorsView server-side search/status', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

  it('sends the search term to the server (not just filtering loaded rows)', async () => {
    const { el, unmount } = await mountView();
    getSensors.mockClear();

    const filterInput = el.querySelector('input[aria-label="Buscar sensores"]');
    expect(filterInput).not.toBeNull();
    filterInput.value = 'temperature';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    expect(getSensors).toHaveBeenCalled();
    const params = getSensors.mock.calls.at(-1)[0];
    expect(params.search).toBe('temperature');
    expect(params.page).toBe(1);

    unmount();
  });

  it('sends the status filter to the server', async () => {
    const { el, unmount } = await mountView();
    getSensors.mockClear();

    const statusSelect = el.querySelector('select[aria-label="Estado del sensor"]');
    expect(statusSelect).not.toBeNull();
    statusSelect.value = 'inactive';
    statusSelect.dispatchEvent(new Event('change'));
    await nextTick();

    await waitDebounce();
    await flush();

    const params = getSensors.mock.calls.at(-1)[0];
    expect(params.status).toBe('inactive');

    unmount();
  });
});

describe('SensorsView search does not reload form catalogs', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
    getSensors.mockResolvedValue(makeSensorResponse([{ id: 1, name: 'Sensor 1', device_id: 1, sensor_type_id: 1, status: true }]));
    getDevices.mockResolvedValue({ data: { data: [{ id: 5, name: 'Device 5', status: true, is_active: true }], current_page: 1, last_page: 1, total: 1, per_page: 20 } });
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

  it('main sensor search calls getSensors but NOT getDevices', async () => {
    const { el, unmount } = await mountView();
    getSensors.mockClear();
    getDevices.mockClear();

    const filterInput = el.querySelector('input[aria-label="Buscar sensores"]');
    filterInput.value = 'temperature';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    expect(getSensors).toHaveBeenCalled();
    expect(getDevices).not.toHaveBeenCalled();

    unmount();
  });

  it('main sensor search calls getSensors but NOT getSensorTypes', async () => {
    const { el, unmount } = await mountView();
    getSensors.mockClear();
    const getSensorTypes = vi.mocked(await import('@/api/catalogs')).getSensorTypes;
    getSensorTypes.mockClear();

    const filterInput = el.querySelector('input[aria-label="Buscar sensores"]');
    filterInput.value = 'temperature';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    expect(getSensors).toHaveBeenCalled();
    expect(getSensorTypes).not.toHaveBeenCalled();

    unmount();
  });

  it('status filter only refreshes sensor list', async () => {
    const { el, unmount } = await mountView();
    getSensors.mockClear();
    getDevices.mockClear();

    const statusSelect = el.querySelector('select[aria-label="Estado del sensor"]');
    statusSelect.value = 'inactive';
    statusSelect.dispatchEvent(new Event('change'));
    await nextTick();

    await waitDebounce();
    await flush();

    expect(getSensors).toHaveBeenCalled();
    expect(getDevices).not.toHaveBeenCalled();

    unmount();
  });
});

describe('SensorsView device selector search error handling', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
    getSensors.mockResolvedValue(makeSensorResponse([{ id: 1, name: 'Sensor 1', device_id: 1, sensor_type_id: 1, status: true }]));
    getDevices.mockRejectedValue(new Error('network error'));
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

  it('device selector search failure does not cause unhandled rejection', async () => {
    const { el, unmount } = await mountView();

    // Open the create form to trigger device selector
    const createButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Nuevo sensor'));
    createButton.click();
    await nextTick();
    await flush();

    const deviceSearchInput = el.querySelector('input[aria-label="Buscar dispositivo para sensor"]');
    expect(deviceSearchInput).not.toBeNull();

    deviceSearchInput.value = 'device';
    deviceSearchInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    // Should not throw unhandled rejection
    // The error should be caught and handled gracefully
    unmount();
  });
});

describe('SensorsView unmount cleanup', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
    getSensors.mockResolvedValue(makeSensorResponse([{ id: 1, name: 'Sensor 1', device_id: 1, sensor_type_id: 1, status: true }]));
    getDevices.mockResolvedValue({ data: { data: [{ id: 5, name: 'Device 5', status: true, is_active: true }], current_page: 1, last_page: 1, total: 1, per_page: 20 } });
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

  it('unmount before 300ms cancels delayed sensor search request', async () => {
    const { el, unmount } = await mountView();
    getSensors.mockClear();

    const filterInput = el.querySelector('input[aria-label="Buscar sensores"]');
    filterInput.value = 'temperature';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    // Unmount before debounce fires
    unmount();
    await waitDebounce();
    await flush();

    // Request should not have been made
    expect(getSensors).not.toHaveBeenCalled();
  });

  it('unmount before 300ms cancels delayed device search request', async () => {
    const { el, unmount } = await mountView();

    const createButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Nuevo sensor'));
    createButton.click();
    await nextTick();
    await flush();

    // Initial load of form catalogs calls getDevices once
    const initialCallCount = getDevices.mock.calls.length;

    const deviceSearchInput = el.querySelector('input[aria-label="Buscar dispositivo para sensor"]');
    deviceSearchInput.value = 'device';
    deviceSearchInput.dispatchEvent(new Event('input'));
    await nextTick();

    unmount();
    await waitDebounce();
    await flush();

    // No additional calls beyond the initial form catalog load
    expect(getDevices).toHaveBeenCalledTimes(initialCallCount);
  });
});

describe('SensorsView rapid search race condition', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

  it('rapid search A -> B -> only B result remains visible', async () => {
    let resolveA;
    let resolveB;
    const promiseA = new Promise(r => { resolveA = r; });
    const promiseB = new Promise(r => { resolveB = r; });

    getSensors
      .mockImplementationOnce(() => promiseA) // search A
      .mockImplementationOnce(() => promiseB); // search B

    const { el, unmount } = await mountView();

    const filterInput = el.querySelector('input[aria-label="Buscar sensores"]');
    filterInput.value = 'A';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    filterInput.value = 'B';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    // Resolve B first, then A (stale)
    resolveB(makeSensorResponse([{ id: 2, name: 'Sensor B' }], { page: 1, lastPage: 1, total: 1 }));
    await waitDebounce();
    await flush();

    resolveA(makeSensorResponse([{ id: 1, name: 'Sensor A' }], { page: 1, lastPage: 1, total: 1 }));
    await waitDebounce();
    await flush();

    // Only B should be visible
    expect(el.textContent).toContain('Sensor B');
    expect(el.textContent).not.toContain('Sensor A');

    unmount();
  });
});

describe('SensorsView loading race condition (FRONT-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
    getDevices.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0, per_page: 20 } });
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

  it('keeps loading=true while a newer (superseding) request is pending, even if the older stale request resolves first', async () => {
    let resolveA;
    let resolveB;
    const promiseA = new Promise((resolve) => { resolveA = resolve; });
    const promiseB = new Promise((resolve) => { resolveB = resolve; });

    getSensors
      .mockImplementationOnce(() => promiseA) // generation 1: initial mount load
      .mockImplementationOnce(() => promiseB); // generation 2: search-triggered reload, supersedes A

    const { el, unmount } = await mountView();

    // Generation 1 (A) is still pending -> spinner visible.
    expect(el.textContent).toContain('Cargando sensores...');

    // Trigger a newer request (generation 2 / B) via search debounce -> reset() + loadFirstPage().
    const filterInput = el.querySelector('input[aria-label="Buscar sensores"]');
    filterInput.value = 'temperature';
    filterInput.dispatchEvent(new Event('input'));
    await waitDebounce();
    await flush();
    await nextTick();

    // B is now the active generation and still in flight.
    expect(el.textContent).toContain('Cargando sensores...');

    // Resolve the STALE request A while B is still pending. Because loading is now the same
    // generation-safe ref usePaginatedList owns (not a second view-level ref), A's finally
    // must not flip loading to false -- it belongs to a superseded generation.
    resolveA(makeSensorResponse([{ id: 1, name: 'Sensor A' }]));
    await flush();
    await nextTick();

    expect(el.textContent).toContain('Cargando sensores...');

    // Resolve B, the current generation -- loading must now become false.
    resolveB(makeSensorResponse([{ id: 2, name: 'Sensor B' }]));
    await flush();
    await nextTick();

    expect(el.textContent).not.toContain('Cargando sensores...');
    expect(el.textContent).toContain('Sensor B');

    unmount();
  });
});

describe('SensorsView pagination', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentQuery = {};
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

  it('loads page 2 when "Cargar más" is clicked', async () => {
    const page1 = makeSensorResponse([{ id: 1 }, { id: 2 }], { page: 1, lastPage: 2, total: 4 });
    const page2 = makeSensorResponse([{ id: 3 }, { id: 4 }], { page: 2, lastPage: 2, total: 4 });
    getSensors
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);

    const { el, unmount } = await mountView();

    await flush();
    await nextTick();

    expect(getSensors).toHaveBeenCalledTimes(1);
    expect(el.textContent).toContain('Sensor 1');
    expect(el.textContent).toContain('Sensor 2');

    // Click "Cargar más"
    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    expect(loadMoreButton).not.toBeNull();
    loadMoreButton.click();
    await flush();
    await nextTick();

    expect(getSensors).toHaveBeenCalledTimes(2);
    expect(getSensors).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 }));
    expect(el.textContent).toContain('Sensor 3');
    expect(el.textContent).toContain('Sensor 4');

    unmount();
  });

  it('search after additional pages resets to page 1', async () => {
    const page1 = makeSensorResponse([{ id: 1 }, { id: 2 }], { page: 1, lastPage: 2, total: 4 });
    const page2 = makeSensorResponse([{ id: 3 }, { id: 4 }], { page: 2, lastPage: 2, total: 4 });
    const searchPage1 = makeSensorResponse([{ id: 5 }, { id: 6 }], { page: 1, lastPage: 1, total: 2 });
    getSensors
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2)
      .mockResolvedValueOnce(searchPage1);

    const { el, unmount } = await mountView();

    await flush();
    await nextTick();

    // Load page 2
    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    // Now search
    const filterInput = el.querySelector('input[aria-label="Buscar sensores"]');
    filterInput.value = 'search';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    // Should have called loadFirstPage with search (page 1)
    expect(getSensors).toHaveBeenCalledTimes(3);
    const lastCall = getSensors.mock.calls.at(-1)[0];
    expect(lastCall.page).toBe(1);
    expect(lastCall.search).toBe('search');

    unmount();
  });

  it('handles empty page gracefully', async () => {
    const page1 = makeSensorResponse([{ id: 1 }], { page: 1, lastPage: 2, total: 1 });
    const page2 = makeSensorResponse([], { page: 2, lastPage: 2, total: 1 });
    getSensors
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);

    const { el, unmount } = await mountView();

    await flush();
    await nextTick();

    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    expect(getSensors).toHaveBeenCalledTimes(2);
    // Should not duplicate items
    expect(el.querySelectorAll('tbody tr').length).toBe(1);

    unmount();
  });

  it('handles API failure during load-more', async () => {
    const page1 = makeSensorResponse([{ id: 1 }], { page: 1, lastPage: 2, total: 2 });
    getSensors
      .mockResolvedValueOnce(page1)
      .mockRejectedValueOnce(new Error('network error'));

    const { el, unmount } = await mountView();

    await flush();
    await nextTick();

    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    // Should not crash, error should be handled
    expect(el.textContent).toContain('Sensor 1');

    unmount();
  });

  it('no duplicated sensors when pages merge', async () => {
    const page1 = makeSensorResponse([{ id: 1 }, { id: 2 }], { page: 1, lastPage: 2, total: 4 });
    const page2 = makeSensorResponse([{ id: 3 }, { id: 4 }], { page: 2, lastPage: 2, total: 4 });
    getSensors
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);

    const { el, unmount } = await mountView();

    await flush();
    await nextTick();

    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    const sensorIds = [...el.querySelectorAll('tbody tr')].map(row => row.textContent.match(/Sensor (\d+)/)?.[1]);
    expect(sensorIds).toEqual(['1', '2', '3', '4']);

    unmount();
  });
});