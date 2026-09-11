<template>
  <section class="lab-resource-page settings-page">
    <div class="lab-toolbar lab-resource-toolbar settings-toolbar">
      <div>
        <RouterLink class="settings-back" to="/config"><LabIcon name="left" /> Volver a Configuración</RouterLink>
        <h1 class="lab-resource-title">Configuración general</h1>
        <p class="lab-resource-description">Define cómo se identifica la aplicación para el equipo y los enlaces compartidos.</p>
      </div>
      <span class="settings-section-badge is-info"><LabIcon name="settings" /> General</span>
    </div>
    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <LoadingSpinner v-if="loading" label="Cargando configuración general..." />
    <form v-else class="settings-form-panel" @submit.prevent="save">
      <div class="settings-form-heading"><h2>Identidad de la aplicación</h2><p>Estos datos no cambian la seguridad ni las credenciales del servicio.</p></div>
      <div class="row g-3">
        <div class="col-12 col-lg-6"><BaseInput v-model="form.app_name" label="Nombre de la aplicación" name="app_name" :error="fieldError('app_name')" required /></div>
        <div class="col-12 col-lg-6"><BaseInput v-model="form.app_url" label="URL de la aplicación" name="app_url" :error="fieldError('app_url')" required /></div>
      </div>
      <div class="settings-form-actions"><BaseButton type="submit" :loading="saving"><LabIcon name="save" /> Guardar cambios</BaseButton></div>
    </form>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { getGeneralConfig, updateGeneralConfig } from '@/api/config';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import LabIcon from '@/components/dashboard/lab/LabIcon.vue';
import { validationMessage } from '@/utils/formatters';

const form = reactive({ app_name: '', app_url: '' });
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const success = ref('');
const errors = ref({});

function fieldError(field) { return validationMessage(errors.value, field); }
async function load() {
  loading.value = true; error.value = '';
  try { Object.assign(form, unwrapData(await getGeneralConfig()) || {}); }
  catch (requestError) { error.value = getApiErrorMessage(requestError, 'No se pudo cargar la configuración general.'); }
  finally { loading.value = false; }
}
async function save() {
  saving.value = true; error.value = ''; success.value = ''; errors.value = {};
  try {
    const response = await updateGeneralConfig({ app_name: form.app_name, app_url: form.app_url });
    Object.assign(form, unwrapData(response) || {});
    success.value = response.data?.message || 'Configuración general actualizada.';
  } catch (requestError) { errors.value = getValidationErrors(requestError); error.value = getApiErrorMessage(requestError, 'No se pudo guardar la configuración general.'); }
  finally { saving.value = false; }
}
onMounted(load);
</script>
