import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function makeDeviceResponse(data, meta = {}) {
  return { data: { data, current_page: meta.page ?? 1, last_page: meta.lastPage ?? 1, total: meta.total ?? data.length, per_page: meta.perPage ?? 50 } };
}

const getDevices = vi.fn(() => Promise.resolve(makeDeviceResponse([
  { id: 1, name: 'Dispositivo 1', status: true, is_active: true, device_type: null, lab: null, sensors: [] }
])));
const updateDeviceStatus = vi.fn(() => Promise.resolve({
  data: { message: 'ok', device: { id: 1, status: false, is_active: false } }
}));
const createDevice = vi.fn();
const updateDevice = vi.fn();
const getLabs = vi.fn(() => Promise.resolve({ data: [] }));
const getDeviceTypes = vi.fn(() => Promise.resolve({ data: [] }));

vi.mock('@/api/devices', () => ({
  getDevices: (...args) => getDevices(...args),
  getDevice: vi.fn(),
  createDevice: (...args) => createDevice(...args),
  updateDevice: (...args) => updateDevice(...args),
  deleteDevice: vi.fn(),
  updateDeviceStatus: (...args) => updateDeviceStatus(...args),
  getDeviceSensors: vi.fn()
}));

vi.mock('@/api/catalogs', () => ({
  getDeviceTypes: (...args) => getDeviceTypes(...args),
  getLabs: (...args) => getLabs(...args)
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const mountedApps = [];

async function mountDevicesView({ permissions = ['device.create', 'device.update'] } = {}) {
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
  authStore.user = { id: 1, name: 'Admin', role: { code: 'superadmin', level: 3 }, permissions };
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  await flush();
  await nextTick();
  return {
    el,
    pinia,
    authStore,
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
    getDevices.mockResolvedValue(makeDeviceResponse([
      { id: 1, name: 'Device 1', status: true, is_active: true, device_type: null, lab: null, sensors: [] }
    ]));
    updateDeviceStatus.mockResolvedValue({
      data: { message: 'ok', device: { id: 1, status: false, is_active: false } }
    });
    createDevice.mockResolvedValue({ data: { message: 'created', api_key: 'created-device-secret' } });
    updateDevice.mockResolvedValue({ data: { message: 'updated', api_key: 'must-not-be-displayed' } });
    getLabs.mockResolvedValue({ data: [] });
    getDeviceTypes.mockResolvedValue({ data: [] });
    localStorage.clear();
    sessionStorage.clear();
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

  it('shows the one-time API key after creation and clears it without persisting it', async () => {
    const { el, pinia, unmount } = await mountDevicesView();

    [...el.querySelectorAll('button')].find((button) => button.textContent.includes('Nuevo dispositivo'))
      .dispatchEvent(new Event('click', { bubbles: true }));
    await nextTick();
    el.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await vi.waitFor(() => expect(createDevice).toHaveBeenCalledOnce());
    await vi.waitFor(() => expect(el.querySelector('#one_time_device_api_key')?.value).toBe('created-device-secret'));

    expect(JSON.stringify(pinia.state.value)).not.toContain('created-device-secret');
    expect(localStorage.getItem('created-device-secret')).toBeNull();
    expect(sessionStorage.getItem('created-device-secret')).toBeNull();

    el.querySelector('button[aria-label="Cerrar"]').dispatchEvent(new Event('click', { bubbles: true }));
    await nextTick();
    expect(el.querySelector('#one_time_device_api_key')).toBeNull();
    expect(JSON.stringify(pinia.state.value)).not.toContain('created-device-secret');

    unmount();
  });

  it('does not show a credential modal when editing a device', async () => {
    const { el, unmount } = await mountDevicesView();

    [...el.querySelectorAll('button')].find((button) => button.textContent.includes('Editar'))
      .dispatchEvent(new Event('click', { bubbles: true }));
    await nextTick();
    el.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await vi.waitFor(() => expect(updateDevice).toHaveBeenCalledWith(1, expect.any(Object)));
    await nextTick();
    expect(el.querySelector('#one_time_device_api_key')).toBeNull();
    expect(el.textContent).not.toContain('must-not-be-displayed');

    unmount();
  });

  it('renders realtime freshness on the device page', async () => {
    const { el, deviceStatuses, unmount } = await mountDevicesView();

    deviceStatuses.setRealtimeStatus({ enabled: true, connected: true, mode: 'stale' });
    await nextTick();

    expect(el.textContent).toContain('Datos posiblemente desactualizados');
    unmount();
  });
});

const waitDebounce = () => new Promise((resolve) => setTimeout(resolve, 350));

describe('DevicesView server-side search', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getDevices.mockResolvedValue(makeDeviceResponse([
      { id: 1, name: 'Device 1', status: true, is_active: true, device_type: null, lab: null, sensors: [] }
    ]));
    updateDeviceStatus.mockResolvedValue({
      data: { message: 'ok', device: { id: 1, status: false, is_active: false } }
    });
    createDevice.mockResolvedValue({ data: { message: 'created', api_key: 'created-device-secret' } });
    updateDevice.mockResolvedValue({ data: { message: 'updated', api_key: 'must-not-be-displayed' } });
    getLabs.mockResolvedValue({ data: [] });
    getDeviceTypes.mockResolvedValue({ data: [] });
    localStorage.clear();
    sessionStorage.clear();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('sends the search term to the server (not just filtering loaded rows)', async () => {
    const { el, unmount } = await mountDevicesView();
    getDevices.mockClear();

    const filterInput = el.querySelector('input[aria-label="Buscar dispositivos"]');
    expect(filterInput).not.toBeNull();
    filterInput.value = 'reactor';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    expect(getDevices).toHaveBeenCalled();
    const params = getDevices.mock.calls.at(-1)[0];
    expect(params.search).toBe('reactor');
    expect(params.page).toBe(1);

    unmount();
  });

  it('does not call getLabs or getDeviceTypes on search', async () => {
    const { el, unmount } = await mountDevicesView();
    getDevices.mockClear();
    getLabs.mockClear();
    getDeviceTypes.mockClear();

    const filterInput = el.querySelector('input[aria-label="Buscar dispositivos"]');
    filterInput.value = 'reactor';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    expect(getDevices).toHaveBeenCalled();
    expect(getLabs).not.toHaveBeenCalled();
    expect(getDeviceTypes).not.toHaveBeenCalled();

    unmount();
  });
});

describe('DevicesView pagination', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    updateDeviceStatus.mockResolvedValue({
      data: { message: 'ok', device: { id: 1, status: false, is_active: false } }
    });
    createDevice.mockResolvedValue({ data: { message: 'created', api_key: 'created-device-secret' } });
    updateDevice.mockResolvedValue({ data: { message: 'updated', api_key: 'must-not-be-displayed' } });
    getLabs.mockResolvedValue({ data: [] });
    getDeviceTypes.mockResolvedValue({ data: [] });
    localStorage.clear();
    sessionStorage.clear();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('loads page 2 when "Cargar más" is clicked', async () => {
    const page1 = makeDeviceResponse([{ id: 1, name: 'Dispositivo 1' }, { id: 2, name: 'Dispositivo 2' }], { page: 1, lastPage: 2, total: 4 });
    const page2 = makeDeviceResponse([{ id: 3, name: 'Dispositivo 3' }, { id: 4, name: 'Dispositivo 4' }], { page: 2, lastPage: 2, total: 4 });
    getDevices
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);

    const { el, unmount } = await mountDevicesView();

    await flush();
    await nextTick();

    expect(getDevices).toHaveBeenCalledTimes(1);
    expect(el.textContent).toContain('Dispositivo 1');
    expect(el.textContent).toContain('Dispositivo 2');

    // Click "Cargar más"
    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    expect(loadMoreButton).not.toBeNull();
    loadMoreButton.click();
    await flush();
    await nextTick();

    expect(getDevices).toHaveBeenCalledTimes(2);
    // Should NOT pass extraParams to loadNextPage - usePaginatedList reuses activeParams
    expect(getDevices).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 }));
    expect(el.textContent).toContain('Dispositivo 3');
    expect(el.textContent).toContain('Dispositivo 4');

    unmount();
  });

  it('search after additional pages resets to page 1', async () => {
    const page1 = makeDeviceResponse([{ id: 1, name: 'Dispositivo 1' }, { id: 2, name: 'Dispositivo 2' }], { page: 1, lastPage: 2, total: 4 });
    const page2 = makeDeviceResponse([{ id: 3, name: 'Dispositivo 3' }, { id: 4, name: 'Dispositivo 4' }], { page: 2, lastPage: 2, total: 4 });
    const searchPage1 = makeDeviceResponse([{ id: 5, name: 'Dispositivo 5' }, { id: 6, name: 'Dispositivo 6' }], { page: 1, lastPage: 1, total: 2 });
    getDevices
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2)
      .mockResolvedValueOnce(searchPage1);

    const { el, unmount } = await mountDevicesView();

    await flush();
    await nextTick();

    // Load page 2
    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    // Now search
    const filterInput = el.querySelector('input[aria-label="Buscar dispositivos"]');
    filterInput.value = 'search';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    await waitDebounce();
    await flush();

    // Should have called loadFirstPage with search (page 1)
    expect(getDevices).toHaveBeenCalledTimes(3);
    const lastCall = getDevices.mock.calls.at(-1)[0];
    expect(lastCall.page).toBe(1);
    expect(lastCall.search).toBe('search');

    unmount();
  });

  it('handles empty page gracefully', async () => {
    const page1 = makeDeviceResponse([{ id: 1, name: 'Dispositivo 1' }], { page: 1, lastPage: 2, total: 1 });
    const page2 = makeDeviceResponse([], { page: 2, lastPage: 2, total: 1 });
    getDevices
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);

    const { el, unmount } = await mountDevicesView();

    await flush();
    await nextTick();

    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    expect(getDevices).toHaveBeenCalledTimes(2);
    // Should not duplicate items
    expect(el.querySelectorAll('tbody tr').length).toBe(1);

    unmount();
  });

  it('handles API failure during load-more', async () => {
    const page1 = makeDeviceResponse([{ id: 1, name: 'Dispositivo 1' }], { page: 1, lastPage: 2, total: 2 });
    getDevices
      .mockResolvedValueOnce(page1)
      .mockRejectedValueOnce(new Error('network error'));

    const { el, unmount } = await mountDevicesView();

    await flush();
    await nextTick();

    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    // Should not crash, error should be handled
    expect(el.textContent).toContain('Dispositivo 1');

    unmount();
  });

  it('no duplicated devices when pages merge', async () => {
    const page1 = makeDeviceResponse([{ id: 1, name: 'Dispositivo 1' }, { id: 2, name: 'Dispositivo 2' }], { page: 1, lastPage: 2, total: 4 });
    const page2 = makeDeviceResponse([{ id: 3, name: 'Dispositivo 3' }, { id: 4, name: 'Dispositivo 4' }], { page: 2, lastPage: 2, total: 4 });
    getDevices
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);

    const { el, unmount } = await mountDevicesView();

    await flush();
    await nextTick();

    const loadMoreButton = [...el.querySelectorAll('button')].find(b => b.textContent.includes('Cargar más'));
    loadMoreButton.click();
    await flush();
    await nextTick();

    const deviceNames = [...el.querySelectorAll('tbody tr')].map(row => row.textContent.match(/Dispositivo (\d+)/)?.[1]);
    expect(deviceNames).toEqual(['1', '2', '3', '4']);

    unmount();
  });
});

describe('DevicesView loading race condition (FRONT-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    createDevice.mockResolvedValue({ data: { message: 'created', api_key: 'created-device-secret' } });
    updateDevice.mockResolvedValue({ data: { message: 'updated', api_key: 'must-not-be-displayed' } });
    getLabs.mockResolvedValue({ data: [] });
    getDeviceTypes.mockResolvedValue({ data: [] });
    localStorage.clear();
    sessionStorage.clear();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('keeps loading=true while a newer (superseding) request is pending, even if the older stale request resolves first', async () => {
    let resolveA;
    let resolveB;
    const promiseA = new Promise((resolve) => { resolveA = resolve; });
    const promiseB = new Promise((resolve) => { resolveB = resolve; });

    getDevices
      .mockImplementationOnce(() => promiseA) // generation 1: initial mount load
      .mockImplementationOnce(() => promiseB); // generation 2: search-triggered reload, supersedes A

    const { el, unmount } = await mountDevicesView();

    // Generation 1 (A) is still pending -> spinner visible.
    expect(el.textContent).toContain('Cargando dispositivos...');

    // Trigger a newer request (generation 2 / B) via search debounce -> reset() + loadFirstPage().
    const filterInput = el.querySelector('input[aria-label="Buscar dispositivos"]');
    filterInput.value = 'Dispositivo';
    filterInput.dispatchEvent(new Event('input'));
    await waitDebounce();
    await flush();
    await nextTick();

    // B is now the active generation and still in flight.
    expect(el.textContent).toContain('Cargando dispositivos...');

    // Resolve the STALE request A while B is still pending. Because loading is now the same
    // generation-safe ref usePaginatedList owns (not a second view-level ref), A's finally
    // must not flip loading to false -- it belongs to a superseded generation.
    resolveA(makeDeviceResponse([{ id: 1, name: 'Dispositivo 1' }]));
    await flush();
    await nextTick();

    expect(el.textContent).toContain('Cargando dispositivos...');

    // Resolve B, the current generation -- loading must now become false.
    resolveB(makeDeviceResponse([{ id: 2, name: 'Dispositivo 2' }]));
    await flush();
    await nextTick();

    expect(el.textContent).not.toContain('Cargando dispositivos...');
    expect(el.textContent).toContain('Dispositivo 2');

    unmount();
  });
});

describe('DevicesView unmount cleanup', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getDevices.mockResolvedValue(makeDeviceResponse([
      { id: 1, name: 'Device 1', status: true, is_active: true, device_type: null, lab: null, sensors: [] }
    ]));
    updateDeviceStatus.mockResolvedValue({
      data: { message: 'ok', device: { id: 1, status: false, is_active: false } }
    });
    createDevice.mockResolvedValue({ data: { message: 'created', api_key: 'created-device-secret' } });
    updateDevice.mockResolvedValue({ data: { message: 'updated', api_key: 'must-not-be-displayed' } });
    getLabs.mockResolvedValue({ data: [] });
    getDeviceTypes.mockResolvedValue({ data: [] });
    localStorage.clear();
    sessionStorage.clear();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it('unmount before 300ms cancels delayed search request', async () => {
    const { el, unmount } = await mountDevicesView();
    getDevices.mockClear();

    const filterInput = el.querySelector('input[aria-label="Buscar dispositivos"]');
    filterInput.value = 'reactor';
    filterInput.dispatchEvent(new Event('input'));
    await nextTick();

    // Unmount before debounce fires
    unmount();
    await waitDebounce();
    await flush();

    // Request should not have been made
    expect(getDevices).not.toHaveBeenCalled();
  });
});