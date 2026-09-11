<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">Reglas de alerta</h1>
        <p class="lab-resource-description">Configura los límites y las condiciones que generan alertas.</p>
      </div>
      <div class="lab-resource-actions">
        <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load"><I name="refresh" />Actualizar</button>
        <button class="btn btn-primary" type="button" :disabled="metadataLoading" @click="openCreate"><I name="plus" />Nueva regla</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />

    <div class="lab-resource-filters">
      <label class="form-label lab-resource-filter-label" for="device_filter"><I name="filter" />Dispositivo</label>
      <select
        id="device_filter"
        v-model="selectedDeviceId"
        class="form-select"
        aria-label="Filtrar reglas por dispositivo"
        @change="load"
      >
        <option value="">Todos</option>
        <option v-for="device in metadata.devices" :key="device.id" :value="device.id">{{ device.name }}</option>
      </select>
    </div>

    <LoadingSpinner v-if="loading" label="Cargando reglas..." />

    <AlertRuleList
      v-if="!loading"
      :rules="rules"
      :deleting-id="deletingId"
      @edit="openEdit"
      @delete="deleteRule"
    />

    <AlertRuleModal :show="modalOpen" :title="selectedRule ? 'Editar regla' : 'Nueva regla'" @close="closeModal">
      <BaseAlert v-if="formError" variant="danger" :message="formError" />
      <AlertRuleForm
        :rule="selectedRule"
        :metadata="metadata"
        :loading="saving"
        :errors="validationErrors"
        @submit="saveRule"
        @cancel="closeModal"
      />
    </AlertRuleModal>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import {
  createAlertRule,
  deleteAlertRule,
  getAlertRuleMetadata,
  getAlertRules,
  updateAlertRule
} from '@/api/alertRules';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import AlertRuleForm from '@/components/alert-rules/AlertRuleForm.vue';
import AlertRuleList from '@/components/alert-rules/AlertRuleList.vue';
import AlertRuleModal from '@/components/alert-rules/AlertRuleModal.vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import I from '@/components/dashboard/lab/LabIcon.vue';
import { paginatedItems } from '@/utils/formatters';

const rules = ref([]);
const loading = ref(false);
const metadataLoading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const error = ref('');
const success = ref('');
const formError = ref('');
const validationErrors = ref({});
const modalOpen = ref(false);
const selectedRule = ref(null);
const selectedDeviceId = ref('');
const metadata = ref({
  sensor_types: [],
  devices: [],
  sensors: []
});

async function load() {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    const params = { per_page: 50 };
    if (selectedDeviceId.value) {
      params.device_id = selectedDeviceId.value;
    }
    const response = await getAlertRules(params);
    rules.value = paginatedItems(response);
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar las reglas de alerta.');
  } finally {
    loading.value = false;
  }
}

async function loadMetadata() {
  metadataLoading.value = true;

  try {
    const response = await getAlertRuleMetadata();
    metadata.value = {
      sensor_types: response.data?.sensor_types || [],
      devices: response.data?.devices || [],
      sensors: response.data?.sensors || []
    };
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar catalogos para reglas.');
  } finally {
    metadataLoading.value = false;
  }
}

async function openCreate() {
  selectedRule.value = null;
  validationErrors.value = {};
  formError.value = '';
  await loadMetadata();
  modalOpen.value = true;
}

async function openEdit(rule) {
  selectedRule.value = rule;
  validationErrors.value = {};
  formError.value = '';
  await loadMetadata();
  modalOpen.value = true;
}

function closeModal() {
  modalOpen.value = false;
  selectedRule.value = null;
  validationErrors.value = {};
  formError.value = '';
}

async function saveRule(payload) {
  saving.value = true;
  formError.value = '';
  validationErrors.value = {};
  success.value = '';

  try {
    if (selectedRule.value) {
      await updateAlertRule(selectedRule.value.id, payload);
      success.value = 'Regla actualizada correctamente.';
    } else {
      await createAlertRule(payload);
      success.value = 'Regla creada correctamente.';
    }

    closeModal();
    await load();
  } catch (requestError) {
    validationErrors.value = getValidationErrors(requestError);
    formError.value = getApiErrorMessage(requestError, 'No se pudo guardar la regla.');
  } finally {
    saving.value = false;
  }
}

async function deleteRule(rule) {
  const confirmed = window.confirm(`Eliminar la regla "${rule.name || rule.id}"?`);
  if (!confirmed) {
    return;
  }

  deletingId.value = rule.id;
  error.value = '';
  success.value = '';

  try {
    await deleteAlertRule(rule.id);
    success.value = 'Regla eliminada correctamente.';
    await load();
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo eliminar la regla.');
  } finally {
    deletingId.value = null;
  }
}

onMounted(() => {
  load();
  loadMetadata();
});
</script>
