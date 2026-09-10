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
  getRuntimeConfig: vi.fn(),
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

  it('does not increment when a trigger already represented by the authoritative snapshot is redelivered', () => {
    const store = useAlertsStore();

    store.applyActiveSnapshot([{ id: 41, resolved: false }], { count: 1 });
    store.addRealtimeAlert({ id: 41, resolved: false });

    expect(store.unresolvedCount).toBe(1);
    expect(store.seenTriggeredIds).toContain(41);
  });
});

describe('Gate 7.1 idempotent trigger/resolve ledgers', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('increments unresolvedCount once for a duplicate triggered alert', () => {
    const store = useAlertsStore();

    store.addRealtimeAlert({ id: 30, resolved: false });
    store.addRealtimeAlert({ id: 30, resolved: false });

    expect(store.unresolvedCount).toBe(1);
    expect(store.seenTriggeredIds).toEqual([30]);
  });

  it('decrements unresolvedCount once for a duplicate resolved alert', () => {
    const store = useAlertsStore();
    store.addRealtimeAlert({ id: 31, resolved: false });

    const first = store.markAlertResolved(31);
    const second = store.markAlertResolved(31);

    expect(first).toBe(true);
    expect(second).toBe(false);
    expect(store.unresolvedCount).toBe(0);
  });

  it('projects a successful manual resolution through the same idempotent ledger as its durable event', async () => {
    const store = useAlertsStore();
    store.applyActiveSnapshot([{ id: 34, resolved: false }], { count: 1 });

    await store.resolveAlert(34);
    const durableEventApplied = store.markAlertResolved(34);

    expect(store.unresolvedCount).toBe(0);
    expect(durableEventApplied).toBe(false);
    expect(store.seenResolvedIds).toContain(34);
  });

  it('records every visible alert resolved by a successful bulk resolution', async () => {
    const store = useAlertsStore();
    store.applyActiveSnapshot([{ id: 35 }, { id: 36 }], { count: 2 });

    await store.resolveAll();

    expect(store.unresolvedCount).toBe(0);
    expect(store.seenResolvedIds).toEqual(expect.arrayContaining([35, 36]));
    expect(store.markAlertResolved(35)).toBe(false);
  });

  it('decrements only once when a resolve arrives for an alert already evicted from the visible arrays', () => {
    const store = useAlertsStore();
    store.addRealtimeAlert({ id: 32, resolved: false });
    // Simulate eviction from the bounded display arrays without going through the store API.
    store.activeAlerts = [];
    store.items = [];

    store.markAlertResolved(32);
    store.markAlertResolved(32);

    expect(store.unresolvedCount).toBe(0);
  });

  it('returns false and does not mutate state for a non-finite alert id', () => {
    const store = useAlertsStore();
    store.unresolvedCount = 3;

    expect(store.markAlertResolved(undefined)).toBe(false);
    expect(store.markAlertResolved(Number.NaN)).toBe(false);
    expect(store.unresolvedCount).toBe(3);
  });

  it('clearAuthorizedState clears both ledgers', () => {
    const store = useAlertsStore();
    store.addRealtimeAlert({ id: 33, resolved: false });
    store.markAlertResolved(33);

    store.clearAuthorizedState();

    expect(store.seenTriggeredIds).toEqual([]);
    expect(store.seenResolvedIds).toEqual([]);
  });
});
