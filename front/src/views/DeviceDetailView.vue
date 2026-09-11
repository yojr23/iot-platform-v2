<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">{{ device?.name || `Dispositivo ${id}` }}</h1>
        <p class="lab-resource-description">Información del equipo, conexión de red y sensores asociados.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary" to="/devices">Volver</RouterLink>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando dispositivo..." />

    <div v-if="!loading" class="row g-3">
      <div class="col-12 col-xl-4">
        <div class="content-panel p-3">
          <h2 class="h5">Datos</h2>
          <dl class="mb-0">
            <dt>Serial</dt>
            <dd>{{ device?.serial_number || '-' }}</dd>
            <dt>Tipo</dt>
            <dd>{{ device?.device_type?.name || '-' }}</dd>
            <dt>Laboratorio</dt>
            <dd>{{ device?.lab?.name || '-' }}</dd>
            <dt>Ubicacion</dt>
            <dd>{{ device?.location || '-' }}</dd>
            <dt>IP</dt>
            <dd>{{ device?.ip_address || '-' }}</dd>
            <dt>Firmware</dt>
            <dd><span class="badge text-bg-light border">{{ device?.firmware_version || '-' }}</span></dd>
            <dt>Fecha instalacion</dt>
            <dd>{{ device?.install_date || '-' }}</dd>
            <dt>Descripcion</dt>
            <dd>{{ device?.description || '-' }}</dd>
            <dt>Ultima comunicacion</dt>
            <dd>{{ formatDate(device?.last_seen || device?.last_communication) }}</dd>
            <dt>Estado</dt>
            <dd class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge" :class="effectiveDevice?.status && effectiveDevice?.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                {{ effectiveDevice?.status && effectiveDevice?.is_active ? 'Activo' : 'Inactivo' }}
              </span>
              <button
                v-if="authStore.user?.is_admin"
                class="btn btn-sm"
                :class="effectiveDevice?.status && effectiveDevice?.is_active ? 'btn-outline-secondary' : 'btn-success'"
                type="button"
                :disabled="updatingStatus"
                @click="toggleStatus"
              >
                {{ effectiveDevice?.status && effectiveDevice?.is_active ? 'Desactivar' : 'Activar' }}
              </button>
            </dd>
          </dl>

          <div v-if="authStore.user?.is_admin && device?.api_key" class="mt-3">
            <label class="form-label d-block" for="device_api_key">Credencial de ingesta (API key)</label>
            <div class="input-group input-group-sm">
              <input
                id="device_api_key"
                class="form-control font-monospace"
                :type="showApiKey ? 'text' : 'password'"
                :value="device.api_key"
                readonly
              />
              <button class="btn btn-outline-secondary" type="button" @click="showApiKey = !showApiKey">
                {{ showApiKey ? 'Ocultar' : 'Mostrar' }}
              </button>
              <button class="btn btn-outline-secondary" type="button" @click="copyApiKey">Copiar</button>
            </div>
            <p v-if="apiKeyCopied" class="text-success small mb-0 mt-1">Copiado al portapapeles.</p>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-8">
        <div class="content-panel">
          <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <div>
              <h2 class="h5 mb-1">Sensores asociados</h2>
              <p class="text-muted small mb-0">Sensores instalados en este dispositivo.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge text-bg-light border">{{ sensors.length }}</span>
              <RouterLink
                v-if="authStore.user?.is_admin"
                class="btn btn-sm btn-outline-primary"
                :to="`/sensors?device_id=${id}`"
              >
                Añadir sensor
              </RouterLink>
            </div>
          </div>

          <div v-if="sensors.length === 0" class="text-center text-muted py-5">
            No hay sensores asociados.
          </div>
          <div v-else class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Sensor</th>
                  <th>Tipo</th>
                  <th>Última lectura</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sensor in sensors" :key="sensor.id">
                  <td class="fw-semibold">{{ sensor.name }}</td>
                  <td>{{ sensor.sensor_type?.name || '-' }}</td>
                  <td>
                    <template v-if="sensor.latest_reading">
                      {{ formatNumber(sensor.latest_reading.value) }} {{ sensor.unit || '' }}
                      <span class="text-muted small d-block">{{ formatDate(sensor.latest_reading.reading_time) }}</span>
                    </template>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td>
                    <span class="badge" :class="sensor.status ? 'text-bg-success' : 'text-bg-secondary'">
                      {{ sensor.status ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                  <td class="text-end">
                    <RouterLink class="btn btn-sm btn-outline-primary" :to="`/sensors/${sensor.id}`">Ver sensor</RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="content-panel">
          <div class="p-3 border-bottom">
            <h2 class="h5 mb-1">Historial de estado</h2>
            <p class="text-muted small mb-0">Ultimos cambios de estado del dispositivo.</p>
          </div>

          <div v-if="statusLogs.length === 0" class="text-center text-muted py-5">
            Sin cambios de estado registrados.
          </div>
          <div v-else class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(log, index) in statusLogs" :key="index">
                  <td>{{ formatDate(log.changed_at || log.created_at) }}</td>
                  <td>
                    <span class="badge" :class="log.status ? 'text-bg-success' : 'text-bg-secondary'">
                      {{ log.status ? 'Encendido' : 'Apagado' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import { getDevice, getDeviceSensors, updateDeviceStatus } from '@/api/devices';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { useAuthStore } from '@/stores/auth';
import { useDeviceStatusesStore } from '@/stores/deviceStatuses';
import { asArray, formatDate, formatNumber } from '@/utils/formatters';

const props = defineProps({
  id: {
    type: String,
    required: true
  }
});

const authStore = useAuthStore();
const deviceStatuses = useDeviceStatusesStore();
const device = ref(null);
const sensors = ref([]);
const loading = ref(false);
const error = ref('');
const updatingStatus = ref(false);
const showApiKey = ref(false);
const apiKeyCopied = ref(false);

// Gate 8.5: overlay the shared realtime/snapshot projection over the fetched device — the
// projection (fed by useDeviceStatusRealtime, no polling here) owns the live status.
const effectiveDevice = computed(() => {
  if (!device.value) {
    return null;
  }
  const projected = deviceStatuses.statusFor(device.value.id);
  return projected ? { ...device.value, status: projected.status, is_active: projected.is_active } : device.value;
});

const statusLogs = computed(() => asArray(device.value?.status_logs));

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const [deviceResponse, sensorsResponse] = await Promise.all([
      getDevice(props.id),
      getDeviceSensors(props.id)
    ]);
    device.value = unwrapData(deviceResponse);
    if (device.value) {
      deviceStatuses.applySnapshot([device.value]);
    }
    sensors.value = asArray(unwrapData(sensorsResponse));
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo cargar el dispositivo.');
  } finally {
    loading.value = false;
  }
}

async function toggleStatus() {
  if (!authStore.user?.is_admin || !device.value) {
    return;
  }

  updatingStatus.value = true;
  error.value = '';

  try {
    // Gate 8.5: same pattern as DevicesView.toggleDeviceStatus — apply the response into the
    // shared projection, no refetch loop.
    const response = await updateDeviceStatus(device.value.id, {
      status: !(effectiveDevice.value?.status && effectiveDevice.value?.is_active)
    });
    const updatedDevice = unwrapData(response)?.device ?? unwrapData(response);
    if (updatedDevice) {
      deviceStatuses.applySnapshot([updatedDevice]);
    }
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo actualizar el estado del dispositivo.');
  } finally {
    updatingStatus.value = false;
  }
}

async function copyApiKey() {
  if (!device.value?.api_key) {
    return;
  }

  await navigator.clipboard.writeText(device.value.api_key);
  apiKeyCopied.value = true;
  setTimeout(() => {
    apiKeyCopied.value = false;
  }, 2000);
}

onMounted(load);
</script>
