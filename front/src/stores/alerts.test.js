import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('@/api/alerts', () => ({
  getActiveAlerts: vi.fn(),
  getAlerts: vi.fn(),
  getUnresolvedAlerts: vi.fn(),
  resolveAlert: vi.fn(),
  resolveAllAlerts: vi.fn(),
}));

vi.mock('@/api/client', () => ({
  getApiErrorMessage: () => 'request failed',
  unwrapData: (response) => response?.data,
}));

vi.mock('@/api/config', () => ({
  getPublicConfig: vi.fn(),
}));

import { useAlertsStore } from './alerts';

describe('alerts snapshot projection', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it.each([
    [0, 0, 0],
    [8, 8, 8],
    [10, 27, 27],
  ])('uses the server total of %i for a snapshot containing %i alerts', (listSize, count, expectedCount) => {
    const store = useAlertsStore();
    const alerts = Array.from({ length: listSize }, (_, index) => ({ id: index + 1 }));

    store.applyActiveSnapshot(alerts, { count });

    expect(store.activeAlerts).toHaveLength(listSize);
    expect(store.unresolvedCount).toBe(expectedCount);
  });

  it('lets a later authoritative snapshot replace increments from unseen live alerts', () => {
    const store = useAlertsStore();
    store.applyActiveSnapshot([{ id: 1 }], { count: 25 });
    store.addRealtimeAlert({ id: 26, resolved: false });
    store.addRealtimeAlert({ id: 27, resolved: false });

    store.applyActiveSnapshot([{ id: 1 }], { count: 27 });

    expect(store.unresolvedCount).toBe(27);
  });
});
