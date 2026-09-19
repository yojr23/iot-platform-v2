import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function makeRuleResponse(data, meta = {}) {
  return { data: { data, current_page: meta.page ?? 1, last_page: meta.lastPage ?? 1, total: meta.total ?? data.length, per_page: meta.perPage ?? 50 } };
}

const rule = { id: 1, name: 'Temperatura alta', severity: 'warning', min_value: null, max_value: 40, message: 'Alerta', sensor: null, device: { name: 'Reactor 1' }, sensor_type: null };

const getAlertRules = vi.fn(() => Promise.resolve(makeRuleResponse([rule])));
const getAlertRuleMetadata = vi.fn(() => Promise.resolve({
  data: { sensor_types: [], devices: [{ id: 5, name: 'Reactor 1' }], sensors: [] }
}));
const createAlertRule = vi.fn();
const updateAlertRule = vi.fn();
const deleteAlertRule = vi.fn();

vi.mock('@/api/alertRules', () => ({
  getAlertRules: (...args) => getAlertRules(...args),
  getAlertRuleMetadata: (...args) => getAlertRuleMetadata(...args),
  getAlertRule: vi.fn(),
  createAlertRule: (...args) => createAlertRule(...args),
  updateAlertRule: (...args) => updateAlertRule(...args),
  deleteAlertRule: (...args) => deleteAlertRule(...args)
}));

const mountedApps = [];
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountAlertRulesView() {
  const { default: AlertRulesView } = await import('./AlertRulesView.vue');
  const host = document.createElement('div');
  const app = createApp(AlertRulesView);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.mount(host);
  mountedApps.push(app);
  await flush();
  await nextTick();
  return { app, host };
}

describe('AlertRulesView load-more continuation (FRONT-02)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getAlertRuleMetadata.mockResolvedValue({
      data: { sensor_types: [], devices: [{ id: 5, name: 'Reactor 1' }], sensors: [] }
    });
  });
  afterEach(() => mountedApps.splice(0).forEach((app) => app.unmount()));

  it('keeps the selected device_id filter across loadNextPage (page 2 still requests it)', async () => {
    const initialPage = makeRuleResponse([rule], { page: 1, lastPage: 1, total: 1 });
    const filteredPage1 = makeRuleResponse([rule], { page: 1, lastPage: 2, total: 2 });
    const filteredPage2 = makeRuleResponse([{ ...rule, id: 2 }], { page: 2, lastPage: 2, total: 2 });
    getAlertRules
      .mockResolvedValueOnce(initialPage) // mount, no filter
      .mockResolvedValueOnce(filteredPage1); // device_id filter applied (page 1)

    const { host } = await mountAlertRulesView();

    const deviceFilter = host.querySelector('#device_filter');
    expect(deviceFilter).not.toBeNull();
    deviceFilter.value = '5';
    deviceFilter.dispatchEvent(new Event('change'));
    await flush();
    await nextTick();

    getAlertRules.mockClear();
    getAlertRules.mockResolvedValueOnce(filteredPage2);

    const loadMoreButton = [...host.querySelectorAll('button')].find((button) => button.textContent.includes('Cargar más'));
    expect(loadMoreButton).not.toBeUndefined();
    loadMoreButton.dispatchEvent(new Event('click', { bubbles: true }));
    await flush();
    await nextTick();

    expect(getAlertRules).toHaveBeenCalledTimes(1);
    const params = getAlertRules.mock.calls.at(-1)[0];
    expect(params.device_id).toBe(5);
    expect(params.page).toBe(2);
  });

  it('surfaces a controlled error when load-more fails (no unhandled rejection)', async () => {
    const page1 = makeRuleResponse([rule], { page: 1, lastPage: 2, total: 2 });
    getAlertRules.mockResolvedValueOnce(page1).mockRejectedValueOnce(new Error('network error'));

    const { host } = await mountAlertRulesView();

    const loadMoreButton = [...host.querySelectorAll('button')].find((button) => button.textContent.includes('Cargar más'));
    expect(loadMoreButton).not.toBeUndefined();
    loadMoreButton.dispatchEvent(new Event('click', { bubbles: true }));
    await flush();
    await nextTick();

    // getApiErrorMessage() surfaces the underlying error message as a controlled BaseAlert,
    // not an unhandled rejection.
    expect(host.textContent).toContain('network error');
    // The original row must still be present -- a failed load-more must not wipe the list.
    expect(host.textContent).toContain('Temperatura alta');
  });
});
