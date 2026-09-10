import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

import { useDeviceStatusesStore } from './deviceStatuses';

describe('deviceStatuses sequence guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('ignores a duplicate event with the same sequence', () => {
    const store = useDeviceStatusesStore();

    expect(store.applyStatusEvent({ device_id: 1, event_sequence: 12, status: true, is_active: true })).toBe(true);
    expect(store.applyStatusEvent({ device_id: 1, event_sequence: 12, status: false, is_active: false })).toBe(false);
    expect(store.statusFor(1)).toMatchObject({ status: true, is_active: true, event_sequence: 12 });
  });

  it('ignores an out-of-order event (12 then 11)', () => {
    const store = useDeviceStatusesStore();

    store.applyStatusEvent({ device_id: 1, event_sequence: 12, status: true, is_active: true });
    const applied = store.applyStatusEvent({ device_id: 1, event_sequence: 11, status: false, is_active: false });

    expect(applied).toBe(false);
    expect(store.statusFor(1)).toMatchObject({ status: true, event_sequence: 12 });
  });

  it('replaces event 12 with a later event 13', () => {
    const store = useDeviceStatusesStore();

    store.applyStatusEvent({ device_id: 1, event_sequence: 12, status: true, is_active: true });
    const applied = store.applyStatusEvent({ device_id: 1, event_sequence: 13, status: false, is_active: false });

    expect(applied).toBe(true);
    expect(store.statusFor(1)).toMatchObject({ status: false, is_active: false, event_sequence: 13 });
  });

  it('returns false for malformed payloads (non-finite id/sequence)', () => {
    const store = useDeviceStatusesStore();

    expect(store.applyStatusEvent({ device_id: undefined, event_sequence: 1 })).toBe(false);
    expect(store.applyStatusEvent({ device_id: 1, event_sequence: 'nope' })).toBe(false);
    expect(store.statusFor(1)).toBeNull();
  });

  it('seeds from a snapshot, then lets a realtime event override it', () => {
    const store = useDeviceStatusesStore();

    store.applySnapshot([{ id: 1, status: true, is_active: true }]);
    expect(store.statusFor(1)).toMatchObject({ status: true, is_active: true, source: 'snapshot', event_sequence: 0 });

    const applied = store.applyStatusEvent({ device_id: 1, event_sequence: 1, status: false, is_active: false });

    expect(applied).toBe(true);
    expect(store.statusFor(1)).toMatchObject({ status: false, is_active: false, source: 'realtime' });
  });

  it('keeps a sequenced realtime state when an older metadata snapshot arrives afterwards', () => {
    const store = useDeviceStatusesStore();

    store.applyStatusEvent({ device_id: 1, event_sequence: 12, status: false, is_active: false });
    store.applySnapshot([{ id: 1, status: true, is_active: true }]);

    expect(store.statusFor(1)).toMatchObject({
      status: false,
      is_active: false,
      event_sequence: 12,
      source: 'realtime'
    });
  });

  it('clear() resets the projection for logout', () => {
    const store = useDeviceStatusesStore();

    store.applyStatusEvent({ device_id: 1, event_sequence: 1, status: true, is_active: true });
    store.clear();

    expect(store.statusFor(1)).toBeNull();
    expect(store.byDevice).toEqual({});
  });
});
