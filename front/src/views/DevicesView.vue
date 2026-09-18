<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">Dispositivos</h1>
        <p class="lab-resource-description">Consulta el estado de tus equipos y sus sensores asociados.</p>
      </div>
      <div class="lab-resource-actions">
        <DeviceRealtimeStatus />
        <button v-if="authStore.can('device.create')" class="btn btn-primary" type="button" @click="openCreate">
          <I name="plus" />Nuevo dispositivo
        </button>
        <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load"><I name="refresh" />Actualizar</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <DeviceFilters v-model:search="search" />
    <LoadingSpinner v-if="loading" label="Cargando dispositivos..." />
    <DeviceList
      v-if="!loading"
      :devices="filteredDevices"
      :updating-id="updatingId"
      @toggle-status="toggleDeviceStatus"
      @edit="openEdit"
      @delete="deleteSelectedDevice"
    />
    <div v-if="!loading && hasMore" class="text-center mt-3">
      <BaseButton variant="outline-secondary" :loading="loadingMore" @click="loadMore">Cargar más</BaseButton>
    </div>

    <BaseModal
      :show="showForm"
      :title="editingDeviceId ? 'Editar dispositivo' : 'Crear dispositivo'"
      subtitle="Catalogos, laboratorio, red y estado operativo."
      content-class="content-panel"
      @close="closeForm"
    >
        <form @submit.prevent="saveDevice">
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <BaseInput v-model="deviceForm.name" label="Nombre" name="device_name" :error="fieldError('name')" required />
            </div>
            <div class="col-12 col-lg-6">
              <BaseInput v-model="deviceForm.serial_number" label="Serial" name="serial_number" :error="fieldError('serial_number')" required />
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="device_type_id">Tipo</label>
              <select id="device_type_id" v-model="deviceForm.device_type_id" class="form-select" :class="{ 'is-invalid': fieldError('device_type_id') }" required>
                <option value="">Seleccione tipo</option>
                <option v-for="type in deviceTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
              </select>
              <div v-if="fieldError('device_type_id')" class="invalid-feedback">{{ fieldError('device_type_id') }}</div>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="lab_id">Laboratorio</label>
              <select id="lab_id" v-model="deviceForm.lab_id" class="form-select" :class="{ 'is-invalid': fieldError('lab_id') }" required>
                <option value="">Seleccione laboratorio</option>
                <option v-for="lab in labs" :key="lab.id" :value="lab.id">{{ lab.name }}</option>
              </select>
              <div v-if="fieldError('lab_id')" class="invalid-feedback">{{ fieldError('lab_id') }}</div>
            </div>
            <div class="col-12 col-lg-6">
              <BaseInput v-model="deviceForm.ip_address" label="IP" name="ip_address" :error="fieldError('ip_address')" />
            </div>
            <div class="col-12 col-lg-6">
              <BaseInput v-model="deviceForm.mac_address" label="MAC" name="mac_address" :error="fieldError('mac_address')" />
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input id="device_status" v-model="deviceForm.status" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="device_status">Activo</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-outline-secondary" type="button" @click="closeForm">Cancelar</button>
            <BaseButton type="submit" :loading="saving">{{ editingDeviceId ? 'Guardar' : 'Crear' }}</BaseButton>
          </div>
        </form>
    </BaseModal>

    <DeviceApiKeyModal
      :show="Boolean(oneTimeApiKey)"
      :api-key="oneTimeApiKey"
      @close="clearOneTimeApiKey"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

import { getDeviceTypes, getLabs } from '@/api/catalogs';
import { createDevice, deleteDevice, getDevices, updateDevice, updateDeviceStatus } from '@/api/devices';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import I from '@/components/dashboard/lab/LabIcon.vue';
import DeviceApiKeyModal from '@/components/devices/DeviceApiKeyModal.vue';
import DeviceFilters from '@/components/devices/DeviceFilters.vue';
import DeviceList from '@/components/devices/DeviceList.vue';
import DeviceRealtimeStatus from '@/components/devices/DeviceRealtimeStatus.vue';
import { useAuthStore } from '@/stores/auth';
import { useDeviceStatusesStore } from '@/stores/deviceStatuses';
import { usePaginatedList } from '@/composables/usePaginatedList';
import { asArray, paginatedItems, validationMessage } from '@/utils/formatters';
import { createLogger } from '@/utils/logger';

const log = createLogger('DevicesView');

const authStore = useAuthStore();
const deviceStatuses = useDeviceStatusesStore();
const labs = ref([]);
const deviceTypes = ref([]);
const loading = ref(false);
const error = ref('');
const success = ref('');
const updatingId = ref(null);
const showForm = ref(false);
const saving = ref(false);
const editingDeviceId = ref(null);
const validationErrors = ref({});
const deviceForm = ref(defaultDeviceForm());
const search = ref('');
const oneTimeApiKey = ref('');

const deviceList = usePaginatedList(
  (params) => getDevices(params),
  { perPage: 50 }
);
const devices = deviceList.items;
const page = deviceList.page;
const lastPage = deviceList.lastPage;
const loadingMore = deviceList.loadingMore;
const hasMore = deviceList.hasMore;

function defaultDeviceForm() {
  return {
    name: '',
    serial_number: '',
    device_type_id: '',
    lab_id: '',
    ip_address: '',
    mac_address: '',
    status: true
  };
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    log.info('load: fetching devices, page 1');
    const shouldLoadCatalogs = Boolean(authStore.can('device.create'));
    const extraParams = search.value.trim() ? { search: search.value.trim() } : {};
    const [, labsResponse, typesResponse] = await Promise.all([
      deviceList.loadFirstPage(extraParams),
      shouldLoadCatalogs ? getLabs() : Promise.resolve({ data: [] }),
      shouldLoadCatalogs ? getDeviceTypes() : Promise.resolve({ data: [] })
    ]);
    labs.value = asArray(unwrapData(labsResponse));
    deviceTypes.value = asArray(unwrapData(typesResponse));
    log.debug('load: devices=', devices.value.length, 'labs=', labs.value.length);
  } catch (requestError) {
    log.warn('load failed:', requestError?.message);
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar los dispositivos.');
  } finally {
    loading.value = false;
  }
}

async function loadMore() {
  if (loadingMore.value || !hasMore.value) {
    return;
  }
  const extraParams = search.value.trim() ? { search: search.value.trim() } : {};
  try {
    await deviceList.loadNextPage(extraParams);
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar más dispositivos.');
  }
}

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    deviceList.reset();
    load();
  }, 300);
});

// Gate 8.5: overlay the shared realtime/snapshot projection over the metadata list fetched by
// load() — the projection (not this list) owns the current status/is_active.
function effectiveDevice(device) {
  const projected = deviceStatuses.statusFor(device.id);
  return projected ? { ...device, status: projected.status, is_active: projected.is_active } : device;
}

const effectiveDevices = computed(() => devices.value.map(effectiveDevice));

watch(devices, (newDevices) => {
  if (newDevices.length) {
    deviceStatuses.applySnapshot(newDevices);
  }
});

const filteredDevices = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) {
    return effectiveDevices.value;
  }

  return effectiveDevices.value.filter((device) => {
    const haystack = [
      device.name,
      device.serial_number,
      device.device_type?.name,
      device.lab?.name
    ].filter(Boolean).join(' ').toLowerCase();

    return haystack.includes(term);
  });
});

async function toggleDeviceStatus(device) {
  if (!authStore.can('device.update')) {
    return;
  }

  updatingId.value = device.id;
  error.value = '';

  try {
    const response = await updateDeviceStatus(device.id, { status: !(device.status && device.is_active) });
    // Gate 8.5: apply the API's own response into the shared projection instead of a full
    // await load() — one write path (this response), no redundant list refetch just to learn
    // the status this same request already returned.
    const updatedDevice = unwrapData(response)?.device ?? unwrapData(response);
    if (updatedDevice) {
      deviceStatuses.applySnapshot([updatedDevice]);
    }
    success.value = 'Estado del dispositivo actualizado.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo actualizar el estado del dispositivo.');
  } finally {
    updatingId.value = null;
  }
}

function openCreate() {
  editingDeviceId.value = null;
  validationErrors.value = {};
  deviceForm.value = defaultDeviceForm();
  showForm.value = true;
}

function openEdit(device) {
  editingDeviceId.value = device.id;
  validationErrors.value = {};
  deviceForm.value = {
    name: device.name || '',
    serial_number: device.serial_number || '',
    device_type_id: device.device_type_id || device.device_type?.id || '',
    lab_id: device.lab_id || device.lab?.id || '',
    ip_address: device.ip_address || '',
    mac_address: device.mac_address || '',
    status: Boolean(device.status && device.is_active)
  };
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingDeviceId.value = null;
  validationErrors.value = {};
}

function clearOneTimeApiKey() {
  oneTimeApiKey.value = '';
}

function devicePayload() {
  return {
    ...deviceForm.value,
    device_type_id: Number(deviceForm.value.device_type_id),
    lab_id: Number(deviceForm.value.lab_id),
    status: Boolean(deviceForm.value.status)
  };
}

async function saveDevice() {
  saving.value = true;
  error.value = '';
  success.value = '';
  validationErrors.value = {};

  try {
    const isEditing = Boolean(editingDeviceId.value);
    const response = isEditing
      ? await updateDevice(editingDeviceId.value, devicePayload())
      : await createDevice(devicePayload());

    // Laravel returns the plaintext API key only from creation. Capture it before reloading the
    // list, but never attach it to device metadata or persisted application state.
    const apiKey = isEditing ? '' : response.data?.api_key || '';

    success.value = response.data?.message || 'Dispositivo guardado.';
    closeForm();
    oneTimeApiKey.value = apiKey;
    await load();
  } catch (requestError) {
    validationErrors.value = getValidationErrors(requestError);
    error.value = getApiErrorMessage(requestError, 'No se pudo guardar el dispositivo.');
  } finally {
    saving.value = false;
  }
}

async function deleteSelectedDevice(device) {
  if (!window.confirm(`Eliminar ${device.name || 'dispositivo'}?`)) {
    return;
  }

  error.value = '';
  success.value = '';

  try {
    const response = await deleteDevice(device.id);
    success.value = response.data?.message || 'Dispositivo eliminado.';
    await load();
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo eliminar el dispositivo.');
  }
}

function fieldError(field) {
  return validationMessage(validationErrors.value, field);
}

onMounted(load);
</script>
