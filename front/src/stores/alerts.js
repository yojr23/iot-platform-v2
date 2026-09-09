import { defineStore } from 'pinia';

import {
  getActiveAlerts,
  getAlerts,
  getUnresolvedAlerts,
  resolveAlert as resolveAlertRequest,
  resolveAllAlerts
} from '@/api/alerts';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import { getPublicConfig, getRuntimeConfig } from '@/api/config';

function normalizeRealtimeAlert(payload) {
  const alert = payload?.alert ?? payload?.data ?? payload;

  if (!alert || typeof alert !== 'object') {
    return null;
  }

  if (alert.alert_rule || alert.sensor_reading || alert.sensor || alert.device) {
    return alert;
  }

  return {
    id: alert.id,
    message: alert.message,
    severity: alert.severity,
    alert_rule: {
      message: alert.message || 'Alerta generada',
      severity: alert.severity || 'warning'
    },
    sensor: {
      name: alert.sensor_name || 'Sensor desconocido',
      sensor_type: {
        name: alert.sensor_type || '',
        unit: alert.unit || ''
      }
    },
    device: {
      name: alert.device_name || 'Dispositivo desconocido',
      lab: {
        name: alert.lab_name || 'Lab no definido'
      }
    },
    sensor_reading: {
      value: alert.value,
      reading_time: alert.timestamp,
      sensor: {
        name: alert.sensor_name || 'Sensor desconocido'
      }
    },
    resolved: Boolean(alert.resolved),
    created_at: alert.created_at || alert.timestamp || new Date().toISOString()
  };
}

function mergeAlert(list, alert, limit = 50) {
  if (!alert?.id) {
    return list;
  }

  const exists = list.some((item) => Number(item.id) === Number(alert.id));
  const next = exists
    ? list.map((item) => (Number(item.id) === Number(alert.id) ? { ...item, ...alert } : item))
    : [alert, ...list];

  return next.slice(0, limit);
}

export const useAlertsStore = defineStore('alerts', {
  state: () => ({
    items: [],
    activeAlerts: [],
    unresolvedCount: 0,
    latestAlert: null,
    // Honest initial status: no transport engaged yet, not a "polling" fallback claim
    // (audit.md §7 no-hybrid rule) — see useAlertsRealtime.js for the connecting/live/
    // recovering/disconnected/stale vocabulary this gets overwritten with.
    realtimeStatus: {
      enabled: false,
      connected: false,
      mode: 'disconnected',
      channel: null,
      error: null
    },
    soundEnabled: false,
    popupEnabled: true,
    loading: false,
    error: null,
    realtimeReady: false
  }),

  actions: {
    async loadPublicConfig() {
      try {
        const response = await getPublicConfig();
        const config = unwrapData(response) || {};
        this.soundEnabled = Boolean(config.alert_sound_enabled ?? config.alertSoundEnabled ?? false);
        return config;
      } catch {
        this.soundEnabled = false;
        return {};
      }
    },

    async loadRuntimeConfig() {
      try {
        const response = await getRuntimeConfig();
        const config = unwrapData(response) || {};
        this.soundEnabled = Boolean(config.alert_sound_enabled ?? config.alertSoundEnabled ?? false);
        return config;
      } catch {
        this.soundEnabled = false;
        return {};
      }
    },

    clearAuthorizedState() {
      this.items = [];
      this.activeAlerts = [];
      this.unresolvedCount = 0;
      this.latestAlert = null;
      this.soundEnabled = false;
      this.loading = false;
      this.error = null;
      this.realtimeReady = false;
      this.realtimeStatus = {
        enabled: false,
        connected: false,
        mode: 'disconnected',
        channel: null,
        error: null
      };
    },

    applyActiveSnapshot(alerts, { count, notifyNew = false } = {}) {
      const nextActiveAlerts = Array.isArray(alerts) ? alerts : [];
      let newAlerts = [];

      if (notifyNew) {
        const knownIds = new Set([...this.activeAlerts, ...this.items]
          .map((alert) => Number(alert?.id)).filter(Number.isFinite));
        newAlerts = [...nextActiveAlerts]
          .sort((a, b) => Number(a?.id ?? 0) - Number(b?.id ?? 0))
          .filter((alert) => Number.isFinite(Number(alert?.id)) && !knownIds.has(Number(alert.id)))
          .map((alert) => this.addRealtimeAlert(alert))
          .filter(Boolean);
      }

      this.activeAlerts = nextActiveAlerts;
      this.unresolvedCount = Number.isFinite(Number(count))
        ? Number(count)
        : this.activeAlerts.length;
      return newAlerts;
    },

    async fetchActiveAlerts({ silent = false, notifyNew = false, throwOnError = false, apply = true } = {}) {
      if (!silent) {
        this.loading = true;
      }
      this.error = null;
      try {
        const response = await getActiveAlerts();
        const snapshot = {
          alerts: response.data?.alerts ?? [],
          count: response.data?.count,
        };
        if (!apply) {
          return snapshot;
        }
        return this.applyActiveSnapshot(snapshot.alerts, { count: snapshot.count, notifyNew });
      } catch (error) {
        this.error = getApiErrorMessage(error, 'No se pudieron cargar las alertas activas.');
        if (throwOnError) {
          throw error;
        }
        return apply ? [] : { alerts: [], count: 0 };
      } finally {
        this.loading = false;
      }
    },

    async fetchAlerts(params = {}) {
      this.loading = true;
      this.error = null;

      try {
        const response = await getAlerts(params);
        const items = unwrapData(response);
        this.items = Array.isArray(items) ? items : [];
      } catch (error) {
        this.error = getApiErrorMessage(error, 'No se pudieron cargar las alertas.');
      } finally {
        this.loading = false;
      }
    },

    async fetchUnresolved() {
      this.loading = true;
      this.error = null;

      try {
        const response = await getUnresolvedAlerts({ per_page: 10 });
        const payload = response.data;
        const items = unwrapData(response);

        this.items = Array.isArray(items) ? items : [];
        this.unresolvedCount = payload.meta?.total ?? this.items.length;
      } catch (error) {
        this.error = getApiErrorMessage(error, 'No se pudieron cargar las alertas.');
      } finally {
        this.loading = false;
      }
    },

    async resolveAlert(alertId) {
      const response = await resolveAlertRequest(alertId);
      this.items = this.items.map((alert) => (
        Number(alert.id) === Number(alertId) ? { ...alert, resolved: true, resolved_at: new Date().toISOString() } : alert
      ));
      this.activeAlerts = this.activeAlerts.filter((alert) => Number(alert.id) !== Number(alertId));
      this.unresolvedCount = Math.max(0, this.unresolvedCount - 1);
      return response;
    },

    async resolveAll() {
      const response = await resolveAllAlerts();
      this.items = this.items.map((alert) => ({ ...alert, resolved: true, resolved_at: new Date().toISOString() }));
      this.activeAlerts = [];
      this.unresolvedCount = 0;
      return response;
    },

    prependAlert(alert) {
      this.addRealtimeAlert(alert);
    },

    // PLAN.md Stage 5 / ownership F2: this store is now the SOLE realtime alert dedup owner.
    // useAlertsRealtime.js no longer keeps its own seenAlertIds Set — the transport delivers,
    // this projection dedups. ponytail: wasKnown only scans the bounded activeAlerts (20) /
    // items (50) arrays, so an id evicted from both before a redelivery would double-count;
    // acceptable for lean V1 (ADR-2) — upgrade to an unbounded/TTL id ledger here if repeat
    // redelivery beyond those windows is ever observed.
    addRealtimeAlert(payload) {
      const alert = normalizeRealtimeAlert(payload);

      if (!alert?.id) {
        return null;
      }

      const wasKnown = this.activeAlerts.some((item) => Number(item.id) === Number(alert.id))
        || this.items.some((item) => Number(item.id) === Number(alert.id));

      this.activeAlerts = mergeAlert(this.activeAlerts, alert, 20);
      this.items = mergeAlert(this.items, alert, 50);

      if (wasKnown) {
        return null;
      }

      this.latestAlert = { ...alert, received_at: Date.now() };

      if (!alert.resolved) {
        this.unresolvedCount += 1;
      }

      return alert;
    },

    setRealtimeReady(value) {
      this.realtimeReady = value;
    },

    setSoundEnabled(value) {
      this.soundEnabled = Boolean(value);
    },

    setRealtimeStatus(status) {
      this.realtimeStatus = {
        ...this.realtimeStatus,
        ...status
      };
      this.realtimeReady = Boolean(this.realtimeStatus.connected);
    }
  }
});
