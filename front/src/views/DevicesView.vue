<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="h3 mb-1">Dispositivos</h1>
        <p class="text-muted mb-0">Vista de lectura con estado y sensores asociados.</p>
      </div>
      <div class="d-flex gap-2">
        <button v-if="authStore.user?.is_admin" class="btn btn-primary" type="button" @click="openCreate">
          Nuevo dispositivo
        </button>
        <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load">Actualizar</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <LoadingSpinner v-if="loading" label="Cargando dispositivos..." />
    <DeviceList
      v-if="!loading"
      :devices="devices"
      :updating-id="updatingId"
      @toggle-status="toggleDeviceStatus"
      @edit="openEdit"
      @delete="deleteSelectedDevice"
    />

    <div v-if="showForm" class="phase-modal-backdrop">
      <div class="phase-modal content-panel p-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h2 class="h5 mb-1">{{ editingDeviceId ? 'Editar dispositivo' : 'Crear dispositivo' }}</h2>
            <p class="text-muted small mb-0">Catalogos, laboratorio, red y estado operativo.</p>
          </div>
          <button class="btn-close" type="button" aria-label="Cerrar" @click="closeForm" />
        </div>

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
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import { getDeviceTypes, getLabs } from '@/api/catalogs';
import { createDevice, deleteDevice, getDevices, updateDevice, updateDeviceStatus } from '@/api/devices';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import DeviceList from '@/components/devices/DeviceList.vue';
import { useAuthStore } from '@/stores/auth';
import { asArray, paginatedItems, validationMessage } from '@/utils/formatters';

const authStore = useAuthStore();
const devices = ref([]);
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
    const shouldLoadCatalogs = Boolean(authStore.user?.is_admin);
    const [devicesResponse, labsResponse, typesResponse] = await Promise.all([
      getDevices({ per_page: 50 }),
      shouldLoadCatalogs ? getLabs() : Promise.resolve({ data: [] }),
      shouldLoadCatalogs ? getDeviceTypes() : Promise.resolve({ data: [] })
    ]);
    const response = devicesResponse;
    devices.value = paginatedItems(response);
    labs.value = asArray(unwrapData(labsResponse));
    deviceTypes.value = asArray(unwrapData(typesResponse));
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar los dispositivos.');
  } finally {
    loading.value = false;
  }
}

async function toggleDeviceStatus(device) {
  if (!authStore.user?.is_admin) {
    return;
  }

  updatingId.value = device.id;
  error.value = '';

  try {
    await updateDeviceStatus(device.id, { status: !(device.status && device.is_active) });
    success.value = 'Estado del dispositivo actualizado.';
    await load();
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
    const response = editingDeviceId.value
      ? await updateDevice(editingDeviceId.value, devicePayload())
      : await createDevice(devicePayload());

    success.value = response.data?.message || 'Dispositivo guardado.';
    closeForm();
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
