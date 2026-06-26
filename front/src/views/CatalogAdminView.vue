<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <p class="section-kicker text-primary mb-2">Catalogos</p>
        <h1 class="h3 mb-1">{{ config.title }}</h1>
        <p class="text-muted mb-0">{{ config.description }}</p>
      </div>
      <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load">Actualizar</button>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />

    <div class="row g-3">
      <div class="col-12 col-xl-4">
        <div class="content-panel p-3">
          <h2 class="h5 mb-3">{{ editingId ? 'Editar' : 'Crear' }}</h2>
          <form @submit.prevent="save">
            <div v-for="field in config.fields" :key="field.name" class="mb-3">
              <label class="form-label" :for="field.name">{{ field.label }}</label>
              <textarea
                v-if="field.type === 'textarea'"
                :id="field.name"
                v-model="form[field.name]"
                class="form-control"
                :class="{ 'is-invalid': fieldError(field.name) }"
                rows="3"
              />
              <input
                v-else
                :id="field.name"
                v-model="form[field.name]"
                class="form-control"
                :class="{ 'is-invalid': fieldError(field.name) }"
                :type="field.type || 'text'"
                :required="field.required !== false"
              />
              <div v-if="fieldError(field.name)" class="invalid-feedback">{{ fieldError(field.name) }}</div>
            </div>

            <div class="d-flex gap-2">
              <BaseButton type="submit" :loading="saving">{{ editingId ? 'Guardar' : 'Crear' }}</BaseButton>
              <button v-if="editingId" class="btn btn-outline-secondary" type="button" @click="resetForm">Cancelar</button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-12 col-xl-8">
        <div class="content-panel">
          <LoadingSpinner v-if="loading" label="Cargando catalogo..." />
          <div v-else-if="items.length === 0" class="text-center text-muted py-5">
            No hay registros.
          </div>
          <div v-else class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th v-for="column in config.columns" :key="column.key">{{ column.label }}</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td v-for="column in config.columns" :key="column.key">{{ valueFor(item, column.key) }}</td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de catalogo">
                      <button class="btn btn-outline-secondary" type="button" @click="edit(item)">Editar</button>
                      <button
                        class="btn btn-outline-danger"
                        type="button"
                        :disabled="deletingId === item.id"
                        @click="remove(item)"
                      >
                        Eliminar
                      </button>
                    </div>
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
import { computed, onMounted, reactive, ref, watch } from 'vue';

import {
  createDeviceType,
  createLab,
  createSensorType,
  deleteDeviceType,
  deleteLab,
  deleteSensorType,
  getDeviceTypes,
  getLabs,
  getSensorTypes,
  updateDeviceType,
  updateLab,
  updateSensorType
} from '@/api/catalogs';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { asArray, validationMessage } from '@/utils/formatters';

const props = defineProps({
  type: {
    type: String,
    required: true
  }
});

const catalogConfigs = {
  labs: {
    title: 'Laboratorios',
    description: 'Areas, lineas de proceso y ubicaciones usadas por dispositivos.',
    load: getLabs,
    create: createLab,
    update: updateLab,
    remove: deleteLab,
    fields: [
      { name: 'name', label: 'Nombre' },
      { name: 'area', label: 'Area' },
      { name: 'process_line', label: 'Linea de proceso' },
      { name: 'description', label: 'Descripcion', type: 'textarea', required: false }
    ],
    columns: [
      { key: 'name', label: 'Nombre' },
      { key: 'area', label: 'Area' },
      { key: 'process_line', label: 'Linea' },
      { key: 'devices_count', label: 'Dispositivos' }
    ]
  },
  'sensor-types': {
    title: 'Tipos de sensores',
    description: 'Catalogo de magnitudes, unidades y rangos operativos.',
    load: getSensorTypes,
    create: createSensorType,
    update: updateSensorType,
    remove: deleteSensorType,
    fields: [
      { name: 'name', label: 'Nombre' },
      { name: 'unit', label: 'Unidad' },
      { name: 'min_range', label: 'Rango minimo', type: 'number' },
      { name: 'max_range', label: 'Rango maximo', type: 'number' }
    ],
    columns: [
      { key: 'name', label: 'Nombre' },
      { key: 'unit', label: 'Unidad' },
      { key: 'min_range', label: 'Min' },
      { key: 'max_range', label: 'Max' },
      { key: 'sensors_count', label: 'Sensores' }
    ]
  },
  'device-types': {
    title: 'Tipos de dispositivos',
    description: 'Familias de equipos disponibles para nuevos dispositivos.',
    load: getDeviceTypes,
    create: createDeviceType,
    update: updateDeviceType,
    remove: deleteDeviceType,
    fields: [
      { name: 'name', label: 'Nombre' },
      { name: 'description', label: 'Descripcion', type: 'textarea', required: false }
    ],
    columns: [
      { key: 'name', label: 'Nombre' },
      { key: 'description', label: 'Descripcion' },
      { key: 'devices_count', label: 'Dispositivos' }
    ]
  }
};

const config = computed(() => catalogConfigs[props.type]);
const items = ref([]);
const loading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const form = reactive({});
const errors = ref({});
const error = ref('');
const success = ref('');

function resetForm() {
  editingId.value = null;
  errors.value = {};
  config.value.fields.forEach((field) => {
    form[field.name] = '';
  });
}

function valueFor(item, key) {
  const value = item?.[key];
  return value === null || value === undefined || value === '' ? '-' : value;
}

function payload() {
  return config.value.fields.reduce((data, field) => {
    const rawValue = form[field.name];
    data[field.name] = field.type === 'number' && rawValue !== '' ? Number(rawValue) : rawValue;
    return data;
  }, {});
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await config.value.load();
    items.value = asArray(unwrapData(response));
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo cargar el catalogo.');
  } finally {
    loading.value = false;
  }
}

function edit(item) {
  editingId.value = item.id;
  errors.value = {};
  config.value.fields.forEach((field) => {
    form[field.name] = item[field.name] ?? '';
  });
}

async function save() {
  saving.value = true;
  error.value = '';
  success.value = '';
  errors.value = {};

  try {
    const response = editingId.value
      ? await config.value.update(editingId.value, payload())
      : await config.value.create(payload());

    success.value = response.data?.message || 'Catalogo actualizado.';
    await load();
    resetForm();
  } catch (requestError) {
    errors.value = getValidationErrors(requestError);
    error.value = getApiErrorMessage(requestError, 'No se pudo guardar el registro.');
  } finally {
    saving.value = false;
  }
}

async function remove(item) {
  if (!window.confirm(`Eliminar ${item.name || 'registro'}?`)) {
    return;
  }

  deletingId.value = item.id;
  error.value = '';
  success.value = '';

  try {
    const response = await config.value.remove(item.id);
    success.value = response.data?.message || 'Registro eliminado.';
    await load();
    if (editingId.value === item.id) {
      resetForm();
    }
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo eliminar el registro.');
  } finally {
    deletingId.value = null;
  }
}

function fieldError(field) {
  return validationMessage(errors.value, field);
}

watch(() => props.type, () => {
  resetForm();
  load();
});

onMounted(() => {
  resetForm();
  load();
});
</script>
