import { defineStore } from 'pinia';

// Gate 8.3 — shared device-status projection, sequence-guarded so an out-of-order or
// redelivered DeviceStatusUpdated event can never regress a device's displayed status.
// Views (list/detail/dashboard) read from this store via statusFor()/effectiveDevice()
// overlays instead of refetching device metadata after a status change.
export const useDeviceStatusesStore = defineStore('deviceStatuses', {
  state: () => ({
    byDevice: {} // deviceId -> { status, is_active, changed_at, event_sequence, source }
  }),

  getters: {
    statusFor: (state) => (deviceId) => state.byDevice[Number(deviceId)] || null
  },

  actions: {
    // Returns true if applied, false if ignored (malformed payload, or a duplicate/
    // out-of-order event_sequence that must not regress the current entry).
    applyStatusEvent(payload) {
      const id = Number(payload?.device_id);
      const sequence = Number(payload?.event_sequence);

      if (!Number.isFinite(id) || !Number.isFinite(sequence)) {
        return false;
      }

      const current = this.byDevice[id];
      if (current && Number(current.event_sequence) >= sequence) {
        return false;
      }

      this.byDevice = {
        ...this.byDevice,
        [id]: {
          status: Boolean(payload.status),
          is_active: Boolean(payload.is_active),
          changed_at: payload.changed_at || new Date().toISOString(),
          event_sequence: sequence,
          source: 'realtime'
        }
      };
      return true;
    },

    // Seeds from an authenticated device metadata snapshot (e.g. GET /devices). event_sequence
    // is seeded at 0 so any real DeviceStatusUpdated (sequence >= 1) always wins over it.
    applySnapshot(devices) {
      if (!Array.isArray(devices)) {
        return;
      }

      const next = { ...this.byDevice };
      devices.forEach((device) => {
        const id = Number(device?.id ?? device?.device_id);
        if (!Number.isFinite(id)) {
          return;
        }

        next[id] = {
          status: Boolean(device.status),
          is_active: Boolean(device.is_active),
          changed_at: device.updated_at || device.last_communication || null,
          event_sequence: 0,
          source: 'snapshot'
        };
      });
      this.byDevice = next;
    },

    clear() {
      this.byDevice = {};
    }
  }
});
