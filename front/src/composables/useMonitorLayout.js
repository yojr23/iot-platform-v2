import { reactive, ref } from 'vue';

import { getDashboardPreferences, updateDashboardPreferences } from '@/api/dashboard';
import { useAuthStore } from '@/stores/auth';

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
 * memory-only for guests) and restore logic. Returns reactive state +
 * restore/persist helpers consumed by SensorMonitorBoard.
 *
 * Guest layout is ephemeral — lost on page refresh (intentional per PLAN.md).
 * Authenticated users persist to the server via getDashboardPreferences/
 * updateDashboardPreferences.
 *
 * ponytail: extracted from SensorMonitorBoard.vue to keep that component
 * under 300 lines. This composable owns no chart, no realtime, no device data —
 * only the layout save/load contract.
 */
export function useMonitorLayout(mainMonitor, monitors, firstSelectableSensor, sanitizeMonitor) {
  const authStore = useAuthStore();
  const restoring = ref(false);
  const saveState = ref('clean');
  let persistTimer = null;
  let savedResetTimer = null;

  async function loadSavedLayout() {
    if (!authStore.isAuthenticated) return null;

    try {
      const response = await getDashboardPreferences();
      return response.data?.layout || null;
    } catch {
      return null;
    }
  }

  async function persistPreferences() {
    if (!authStore.isAuthenticated) return;

    const layout = currentLayout(mainMonitor, monitors);

    try {
      saveState.value = 'saving';
      await updateDashboardPreferences({ layout });
      saveState.value = 'saved';
      window.clearTimeout(savedResetTimer);
      savedResetTimer = window.setTimeout(() => { saveState.value = 'clean'; }, 2000);
    } catch {
      saveState.value = 'error';
    }
  }

  function schedulePersist() {
    if (restoring.value) return;

    saveState.value = 'dirty';
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
    window.clearTimeout(savedResetTimer);
  }

  return { restoring, restoreLayout, schedulePersist, cleanup, saveState };
}
