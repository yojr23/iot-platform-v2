import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

const mountedApps = [];

async function mountRealtimeStatus() {
  const { default: DeviceRealtimeStatus } = await import('./DeviceRealtimeStatus.vue');
  const { useDeviceStatusesStore } = await import('@/stores/deviceStatuses');
  const el = document.createElement('div');
  const app = createApp(DeviceRealtimeStatus);
  const pinia = createPinia();
  app.use(pinia);
  setActivePinia(pinia);
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  return { el, store: useDeviceStatusesStore() };
}

describe('DeviceRealtimeStatus', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
  });

  it.each([
    ['live', 'Tiempo real'],
    ['recovering', 'Sincronizando'],
    ['stale', 'Datos posiblemente desactualizados'],
    ['disconnected', 'Desconectado']
  ])('renders %s freshness as %s', async (mode, label) => {
    const { el, store } = await mountRealtimeStatus();

    store.setRealtimeStatus({ enabled: mode !== 'disconnected', connected: mode !== 'disconnected', mode });
    await nextTick();

    expect(el.textContent).toContain(label);
    expect(el.querySelector('[role="status"]')?.getAttribute('data-realtime-mode')).toBe(mode);
  });

  it('shows disconnected after logout or permission revocation clears the projection', async () => {
    const { el, store } = await mountRealtimeStatus();

    store.applyStatusEvent({ device_id: 3, event_sequence: 1, status: true, is_active: true });
    store.setRealtimeStatus({ enabled: true, connected: true, mode: 'live' });
    store.clear();
    await nextTick();

    expect(store.statusFor(3)).toBeNull();
    expect(el.textContent).toContain('Desconectado');
  });
});
