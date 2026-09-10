import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const getActiveAlerts = vi.fn(() => Promise.resolve({ data: { alerts: [], count: 0 } }));

vi.mock('@/api/alerts', () => ({
  getActiveAlerts: (...args) => getActiveAlerts(...args),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn(),
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountActiveAlertsCard() {
  const { default: ActiveAlertsCard } = await import('./ActiveAlertsCard.vue');
  const el = document.createElement('div');
  const app = createApp(ActiveAlertsCard);
  app.mount(el);
  await nextTick();
  await flush();
  return { el, unmount: () => app.unmount() };
}

describe('ActiveAlertsCard is presentation-only (Gate 7.3)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
  });

  it('makes no API request on mount', async () => {
    const { unmount } = await mountActiveAlertsCard();

    expect(getActiveAlerts).not.toHaveBeenCalled();

    unmount();
  });

  it('renders store state without fetching', async () => {
    const { useAlertsStore } = await import('@/stores/alerts');
    const alertsStore = useAlertsStore();
    alertsStore.activeAlerts = [{ id: 1, alert_rule: { message: 'Test alert', severity: 'warning' } }];
    alertsStore.unresolvedCount = 1;

    const { el, unmount } = await mountActiveAlertsCard();

    expect(el.textContent).toContain('Test alert');
    expect(getActiveAlerts).not.toHaveBeenCalled();

    unmount();
  });
});
