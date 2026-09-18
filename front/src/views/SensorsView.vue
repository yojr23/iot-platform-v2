<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">Sensores</h1>
        <p class="lab-resource-description">Explora tus sensores, consulta sus lecturas y gestiona su configuración.</p>
      </div>
      <div class="lab-resource-actions">
        <button v-if="authStore.can('sensor.create')" class="btn btn-primary" type="button" @click="openCreate">
          <I name="plus" />Nuevo sensor
        </button>
        <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load"><I name="refresh" />Actualizar</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <SensorFilters v-model:search="search" v-model:status="statusFilter" />
    <LoadingSpinner v-if="loading" label="Cargando sensores..." />
    <SensorList
      v-if="!loading"
      :sensors="sensors"
      @edit="openEdit"
      @delete="deleteSelectedSensor"
      @export="exportSelectedSensor"
    />
    <div v-if="!loading && hasMore" class="text-center mt-3">
      <BaseButton variant="outline-secondary" :loading="loadingMore" @click="loadNextPage">Cargar más</BaseButton>
    </div>

    <BaseModal
      :show="showForm"
      :title="editingSensorId ? 'Editar sensor' : 'Crear sensor'"
      subtitle="Dispositivo, tipo, estado y nombre operativo."
      content-class="content-panel"
      @close="closeForm"
    >
        <form @submit.prevent="saveSensor">
          <div class="row g-3">
            <div class="col-12">
              <BaseInput v-model="sensorForm.name" label="Nombre" name="sensor_name" :error="fieldError('name')" required />
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="sensor_device_id">Dispositivo</label>
              <input
                v-if="showForm"
                v-model="deviceSearch"
                type="search"
                class="form-control form-control-sm mb-1"
                placeholder="Buscar dispositivo..."
                aria-label="Buscar dispositivo para sensor"
              />
              <select id="sensor_device_id" v-model="sensorForm.device_id" class="form-select" :class="{ 'is-invalid': fieldError('device_id') }" required>
                <option value="">Seleccione dispositivo</option>
                <option v-for="device in filteredFormDevices" :key="device.id" :value="device.id">{{ device.name }}</option>
              </select>
              <div v-if="formDevicesLoading" class="form-text">Cargando dispositivos...</div>
              <div v-if="formDevicesHasMore && !formDevicesLoading" class="form-text">
                Mostrando {{ formDevices.length }} de {{ formDevicesTotal }} dispositivos
              </div>
              <BaseButton variant="outline-secondary" v-if="!formDevicesLoading && formDevicesHasMore" :loading="formDeviceList.loadingMore" @click="loadMoreDevices">Cargar más dispositivos</BaseButton>
              <div v-if="fieldError('device_id')" class="invalid-feedback">{{ fieldError('device_id') }}</div>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="sensor_type_id">Tipo</label>
              <select id="sensor_type_id" v-model="sensorForm.sensor_type_id" class="form-select" :class="{ 'is-invalid': fieldError('sensor_type_id') }" required>
                <option value="">Seleccione tipo</option>
                <option v-for="type in sensorTypes" :key="type.id" :value="type.id">{{ type.name }} ({{ type.unit }})</option>
              </select>
              <div v-if="fieldError('sensor_type_id')" class="invalid-feedback">{{ fieldError('sensor_type_id') }}</div>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input id="sensor_status" v-model="sensorForm.status" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="sensor_status">Activo</label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input id="sensor_public" v-model="sensorForm.public_monitoring_enabled" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="sensor_public">Permitir monitoreo publico en graficas</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-outline-secondary" type="button" @click="closeForm">Cancelar</button>
            <BaseButton type="submit" :loading="saving">{{ editingSensorId ? 'Guardar' : 'Crear' }}</BaseButton>
          </div>
        </form>
    </BaseModal>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import { getSensorTypes } from '@/api/catalogs';
import { getDevices } from '@/api/devices';
import { createSensor, deleteSensor, exportSensorReadings, getSensors, updateSensor } from '@/api/sensors';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import I from '@/components/dashboard/lab/LabIcon.vue';
import SensorFilters from '@/components/sensors/SensorFilters.vue';
import SensorList from '@/components/sensors/SensorList.vue';
import { useAuthStore } from '@/stores/auth';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { asArray, paginatedItems, validationMessage } from '@/utils/formatters';
import { createLogger } from '@/utils/logger';

const log = createLogger('SensorsView');

const authStore = useAuthStore();
const route = useRoute();
const sensorTypes = ref([]);
const loading = ref(false);
const error = ref('');
const success = ref('');
const search = ref('');
const statusFilter = ref('all');
const showForm = ref(false);
const saving = ref(false);
const editingSensorId = ref(null);
const validationErrors = ref({});
const sensorForm = ref(defaultSensorForm());
const deviceSearch = ref('');

const sensorList = usePaginatedList(
  (params) => getSensors({ per_page: 50, ...params }),
  { perPage: 50 }
);
const sensors = sensorList.items;
const hasMore = sensorList.hasMore;
const loadingMore = sensorList.loadingMore;

// Wrapper for loadNextPage with error handling
async function loadNextPage() {
  if (sensorList.loadingMore.value || !sensorList.hasMore.value) {
    return;
  }
  try {
    await sensorList.loadNextPage();
  } catch (requestError) {
    log.warn('loadNextPage failed:', requestError?.message);
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar más sensores.');
  }
}

const formDeviceList = usePaginatedList(
  (params) => getDevices({ per_page: 20, ...params }),
  { perPage: 20 }
);
const formDevices = formDeviceList.items;
const formDevicesLoading = formDeviceList.loading;
const formDevicesHasMore = formDeviceList.hasMore;
const formDevicesTotal = formDeviceList.total;

// Server handles the name search; here we only keep the "selectable device" rule (active).
const filteredFormDevices = computed(() =>
  formDevices.value.filter((d) => d.status && d.is_active)
);

let deviceSearchDebounce = null;
watch(deviceSearch, () => {
  clearTimeout(deviceSearchDebounce);
  deviceSearchDebounce = setTimeout(() => {
    formDeviceList.reset();
    const term = deviceSearch.value.trim();
    formDeviceList.loadFirstPage(term ? { search: term } : {}).catch((err) => {
      log.warn('device search failed:', err?.message);
    });
  }, 300);
});

function defaultSensorForm() {
  return {
    name: '',
    device_id: '',
    sensor_type_id: '',
    status: true,
    public_monitoring_enabled: false
  };
}

// Single source of truth for sensor list params — used by page 1, page 2+, and refresh so
// server-side search/status stay consistent across pages (a match on an unfetched page is
// no longer hidden by a client-only filter).
function sensorListParams() {
  const params = {};
  const term = search.value.trim();
  if (term) {
    params.search = term;
  }
  if (statusFilter.value === 'active' || statusFilter.value === 'inactive') {
    params.status = statusFilter.value;
  }
  return params;
}

async function loadSensorList() {
  loading.value = true;
  error.value = '';

  try {
    log.info('loadSensorList: fetching sensors');
    await sensorList.loadFirstPage(sensorListParams());
    log.debug('loadSensorList: sensors=', sensors.value.length);
  } catch (requestError) {
    log.warn('loadSensorList failed:', requestError?.message);
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar los sensores.');
  } finally {
    loading.value = false;
  }
}

async function loadSensorFormCatalogs() {
  const shouldLoadAdminData = Boolean(authStore.can('sensor.create'));
  if (!shouldLoadAdminData) {
    return;
  }
  try {
    log.info('loadSensorFormCatalogs: fetching form catalogs');
    const [, typesResponse] = await Promise.all([
      formDeviceList.loadFirstPage(),
      getSensorTypes()
    ]);
    sensorTypes.value = asArray(unwrapData(typesResponse));
    log.debug('loadSensorFormCatalogs: done');
  } catch (requestError) {
    log.warn('loadSensorFormCatalogs failed:', requestError?.message);
  }
}

async function load() {
  await Promise.all([
    loadSensorList(),
    loadSensorFormCatalogs()
  ]);
}

let searchDebounce = null;
watch([search, statusFilter], () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    sensorList.reset();
    loadSensorList();
  }, 300);
});

async function loadMoreDevices() {
  if (formDeviceList.loadingMore.value || !formDeviceList.hasMore.value) {
    return;
  }
  try {
    await formDeviceList.loadNextPage();
  } catch (requestError) {
    log.warn('loadMoreDevices failed:', requestError?.message);
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar más dispositivos.');
  }
}

function openCreate() {
  editingSensorId.value = null;
  validationErrors.value = {};
  sensorForm.value = defaultSensorForm();
  deviceSearch.value = '';
  showForm.value = true;
}

function openEdit(sensor) {
  editingSensorId.value = sensor.id;
  validationErrors.value = {};
  sensorForm.value = {
    name: sensor.name || '',
    device_id: sensor.device_id || sensor.device?.id || '',
    sensor_type_id: sensor.sensor_type_id || sensor.sensor_type?.id || '',
    status: Boolean(sensor.status),
    public_monitoring_enabled: Boolean(sensor.public_monitoring_enabled)
  };
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingSensorId.value = null;
  validationErrors.value = {};
}

function sensorPayload() {
  return {
    ...sensorForm.value,
    device_id: Number(sensorForm.value.device_id),
    sensor_type_id: Number(sensorForm.value.sensor_type_id),
    status: Boolean(sensorForm.value.status),
    public_monitoring_enabled: Boolean(sensorForm.value.public_monitoring_enabled)
  };
}

async function saveSensor() {
  saving.value = true;
  error.value = '';
  success.value = '';
  validationErrors.value = {};

  try {
    const response = editingSensorId.value
      ? await updateSensor(editingSensorId.value, sensorPayload())
      : await createSensor(sensorPayload());

    success.value = response.data?.message || 'Sensor guardado.';
    closeForm();
    await load();
  } catch (requestError) {
    validationErrors.value = getValidationErrors(requestError);
    error.value = getApiErrorMessage(requestError, 'No se pudo guardar el sensor.');
  } finally {
    saving.value = false;
  }
}

async function deleteSelectedSensor(sensor) {
  if (!window.confirm(`Eliminar ${sensor.name || 'sensor'}?`)) {
    return;
  }

  error.value = '';
  success.value = '';

  try {
    const response = await deleteSensor(sensor.id);
    success.value = response.data?.message || 'Sensor eliminado.';
    await load();
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo eliminar el sensor.');
  }
}

async function exportSelectedSensor(sensor) {
  // Defense-in-depth: don't rely on the button's v-if or the backend alone — gate the call
  // itself on sensor_reading.export, matching SensorDetailView.exportReadings.
  if (!authStore.can('sensor_reading.export')) {
    return;
  }

  error.value = '';
  success.value = '';

  try {
    const response = await exportSensorReadings(sensor.id);
    const payload = JSON.stringify(response.data, null, 2);
    const blob = new Blob([payload], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sensor_${sensor.id}_readings.json`;
    link.click();
    window.URL.revokeObjectURL(url);
    success.value = 'Lecturas exportadas.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron exportar las lecturas.');
  }
}

function fieldError(field) {
  return validationMessage(validationErrors.value, field);
}

onMounted(async () => {
  await load();

  // D5 (sensor side): honor a `?device_id=` preselect from a device-detail "add sensor" link.
  const deviceId = route.query.device_id;

  if (deviceId && authStore.can('sensor.create')) {
    openCreate();
    sensorForm.value.device_id = String(deviceId);
  }
});

onBeforeUnmount(() => {
  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }
  if (deviceSearchDebounce) {
    clearTimeout(deviceSearchDebounce);
  }
});
</script>
