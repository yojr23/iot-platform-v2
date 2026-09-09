import { defineStore } from 'pinia';

// Stage 8.1 — shared device-status projection owned by realtime.
// DeviceStatusUpdated events arrive on PrivateChannel('device-status') and are
// applied here; Views read from this store instead of refetching after a status change.
// ponytail: keyed by deviceId, deterministic last-event-wins application.
export const useDeviceStatusesStore = defineStore('deviceStatuses', {
  state: () => ({
    byDevice: {} // deviceId -> { status, updated_at, source }
  }),

  getters: {
    statusFor: (state) => (deviceId) => state.byDevice[deviceId] || null
  },

  actions: {
    applyStatusEvent(payload) {
      const event = payload?.device ?? payload?.data ?? payload;
      const deviceId = event?.id ?? event?.device_id;

      if (!deviceId) {
        return;
      }

      this.byDevice = {
        ...this.byDevice,
        [deviceId]: {
          status: event.status ?? event.is_active ?? event.device_status,
          updated_at: event.updated_at || event.timestamp || new Date().toISOString(),
          source: 'realtime'
        }
      };
    },

    applySnapshot(devices) {
      if (!Array.isArray(devices)) {
        return;
      }

      const next = { ...this.byDevice };
      for (const device of devices) {
        const id = device.id ?? device.device_id;
        if (id) {
          next[id] = {
            status: device.status ?? device.is_active ?? device.device_status,
            updated_at: device.updated_at || new Date().toISOString(),
            source: 'snapshot'
          };
        }
      }
      this.byDevice = next;
    },

    clearAll() {
      this.byDevice = {};
    }
  }
});
