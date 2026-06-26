<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="h3 mb-1">Reglas de alerta</h1>
        <p class="text-muted mb-0">CRUD de reglas usando la API JSON.</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load">Actualizar</button>
        <button class="btn btn-primary" type="button" :disabled="metadataLoading" @click="openCreate">Nueva regla</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
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
    const response = await getAlertRules({ per_page: 50 });
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
