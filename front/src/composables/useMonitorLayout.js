import { reactive, ref, watch } from 'vue';

import { getDashboardPreferences, updateDashboardPreferences } from '@/api/dashboard';
import { useAuthStore } from '@/stores/auth';

const LOCAL_STORAGE_KEY = 'iot-platform-v2.dashboard_layout';

function readLocalLayout() {
  try {
    const raw = window.localStorage.getItem(LOCAL_STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function currentLayout(mainMonitor, monitors) {
  return {
    main: {
      device_id: mainMonitor.device_id || null,
      sensor_id: mainMonitor.sensor_id || null,
    },
    monitors: monitors.value.map((m) => ({
      id: m.id,
      device_id: m.device_id || null,
      sensor_id: m.sensor_id || null,
    })),
  };
}

/**
 * Encapsulates dashboard layout persistence (server for authenticated users,
 * localStorage for guests) and restore logic. Returns reactive state +
 * restore/persist helpers consumed by SensorMonitorBoard.
 *
 * ponytail: extracted from SensorMonitorBoard.vue to keep that component
 * under 300 lines. This composable owns no chart, no realtime, no device data —
 * only the layout save/load contract.
 */
export function useMonitorLayout(mainMonitor, monitors, firstSelectableSensor, sanitizeMonitor) {
  const authStore = useAuthStore();
  const restoring = ref(false);
  let persistTimer = null;

  function normalizeId(value) {
    return value === null || value === undefined ? '' : String(value);
  }

  async function loadSavedLayout() {
    if (authStore.isAuthenticated) {
      try {
        const response = await getDashboardPreferences();
        return response.data?.layout || null;
      } catch {
        return readLocalLayout();
      }
    }
    return readLocalLayout();
  }

  async function persistPreferences() {
    const layout = currentLayout(mainMonitor, monitors);

    if (authStore.isAuthenticated) {
      try {
        await updateDashboardPreferences({ layout });
        return;
      } catch {
        window.localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(layout));
        return;
      }
    }

    window.localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(layout));
  }

  function schedulePersist() {
    if (restoring.value) return;

    window.clearTimeout(persistTimer);
    persistTimer = window.setTimeout(persistPreferences, 350);
  }

  async function restoreLayout(devices) {
    if (devices.length === 0) return;

    restoring.value = true;

    const savedLayout = await loadSavedLayout();
    const defaultSelection = firstSelectableSensor();
    const mainSelection = sanitizeMonitor(savedLayout?.main, defaultSelection);

    mainMonitor.device_id = mainSelection.device_id;
    mainMonitor.sensor_id = mainSelection.sensor_id;
    monitors.value = Array.isArray(savedLayout?.monitors)
      ? savedLayout.monitors
          .filter((m) => m?.id)
          .map((m) => ({
            id: m.id,
            ...sanitizeMonitor(m, defaultSelection),
          }))
      : [];

    restoring.value = false;
    schedulePersist();
  }

  function cleanup() {
    window.clearTimeout(persistTimer);
  }

  return { restoring, restoreLayout, schedulePersist, cleanup };
}
