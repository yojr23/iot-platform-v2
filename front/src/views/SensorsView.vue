<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="h3 mb-1">Sensores</h1>
        <p class="text-muted mb-0">Listado operativo con filtro local y acceso al detalle.</p>
      </div>
      <div class="d-flex gap-2">
        <button v-if="authStore.user?.is_admin" class="btn btn-primary" type="button" @click="openCreate">
          Nuevo sensor
        </button>
        <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load">Actualizar</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <SensorFilters v-model:search="search" v-model:status="statusFilter" />
    <LoadingSpinner v-if="loading" label="Cargando sensores..." />
    <SensorList
      v-if="!loading"
      :sensors="filteredSensors"
      @edit="openEdit"
      @delete="deleteSelectedSensor"
      @export="exportSelectedSensor"
    />

    <div v-if="showForm" class="phase-modal-backdrop">
      <div class="phase-modal content-panel p-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h2 class="h5 mb-1">{{ editingSensorId ? 'Editar sensor' : 'Crear sensor' }}</h2>
            <p class="text-muted small mb-0">Dispositivo, tipo, estado y nombre operativo.</p>
          </div>
          <button class="btn-close" type="button" aria-label="Cerrar" @click="closeForm" />
        </div>

        <form @submit.prevent="saveSensor">
          <div class="row g-3">
            <div class="col-12">
              <BaseInput v-model="sensorForm.name" label="Nombre" name="sensor_name" :error="fieldError('name')" required />
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="sensor_device_id">Dispositivo</label>
              <select id="sensor_device_id" v-model="sensorForm.device_id" class="form-select" :class="{ 'is-invalid': fieldError('device_id') }" required>
                <option value="">Seleccione dispositivo</option>
                <option v-for="device in devices" :key="device.id" :value="device.id">{{ device.name }}</option>
              </select>
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
          </div>

          <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-outline-secondary" type="button" @click="closeForm">Cancelar</button>
            <BaseButton type="submit" :loading="saving">{{ editingSensorId ? 'Guardar' : 'Crear' }}</BaseButton>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import { getSensorTypes } from '@/api/catalogs';
import { getDevices } from '@/api/devices';
import { createSensor, deleteSensor, exportSensorReadings, getSensors, updateSensor } from '@/api/sensors';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import SensorFilters from '@/components/sensors/SensorFilters.vue';
import SensorList from '@/components/sensors/SensorList.vue';
import { useAuthStore } from '@/stores/auth';
import { asArray, paginatedItems, validationMessage } from '@/utils/formatters';

const authStore = useAuthStore();
const sensors = ref([]);
const devices = ref([]);
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

function defaultSensorForm() {
  return {
    name: '',
    device_id: '',
    sensor_type_id: '',
    status: true
  };
}

const filteredSensors = computed(() => {
  const term = search.value.trim().toLowerCase();

  return sensors.value.filter((sensor) => {
    const matchesStatus = statusFilter.value === 'all'
      || (statusFilter.value === 'active' && sensor.status)
      || (statusFilter.value === 'inactive' && !sensor.status);

    const haystack = [
      sensor.name,
      sensor.sensor_type?.name,
      sensor.device?.name,
      sensor.unit
    ].filter(Boolean).join(' ').toLowerCase();

    return matchesStatus && (!term || haystack.includes(term));
  });
});

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const shouldLoadAdminData = Boolean(authStore.user?.is_admin);
    const [sensorsResponse, devicesResponse, typesResponse] = await Promise.all([
      getSensors({ per_page: 50 }),
      shouldLoadAdminData ? getDevices({ per_page: 100 }) : Promise.resolve({ data: [] }),
      shouldLoadAdminData ? getSensorTypes() : Promise.resolve({ data: [] })
    ]);
    sensors.value = asArray(unwrapData(sensorsResponse));
    devices.value = paginatedItems(devicesResponse).filter((device) => device.status && device.is_active);
    sensorTypes.value = asArray(unwrapData(typesResponse));
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar los sensores.');
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingSensorId.value = null;
  validationErrors.value = {};
  sensorForm.value = defaultSensorForm();
  showForm.value = true;
}

function openEdit(sensor) {
  editingSensorId.value = sensor.id;
  validationErrors.value = {};
  sensorForm.value = {
    name: sensor.name || '',
    device_id: sensor.device_id || sensor.device?.id || '',
    sensor_type_id: sensor.sensor_type_id || sensor.sensor_type?.id || '',
    status: Boolean(sensor.status)
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
    status: Boolean(sensorForm.value.status)
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

onMounted(load);
</script>
