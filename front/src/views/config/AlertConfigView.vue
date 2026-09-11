<template>
  <section class="lab-resource-page settings-page">
    <div class="lab-toolbar lab-resource-toolbar settings-toolbar">
      <div><RouterLink class="settings-back" to="/config"><LabIcon name="left" /> Volver a Configuración</RouterLink><h1 class="lab-resource-title">Alertas</h1><p class="lab-resource-description">Ajusta las notificaciones y el ritmo de actualización de las alertas.</p></div>
      <span class="settings-section-badge is-warning"><LabIcon name="bell" /> Alertas</span>
    </div>
    <BaseAlert v-if="error" variant="danger" :message="error" /><BaseAlert v-if="success" variant="success" :message="success" />
    <LoadingSpinner v-if="loading" label="Cargando alertas..." />
    <form v-else class="settings-form-panel" @submit.prevent="save">
      <div class="settings-form-heading"><h2>Notificaciones y límites</h2><p>Los cambios se aplican a las alertas generadas a partir de ahora.</p></div>
      <div class="settings-switch-grid">
        <label class="settings-switch"><input v-model="form.mail_enabled" type="checkbox" /><span><strong>Notificaciones por correo</strong><small>Envía alertas al destino SMTP configurado.</small></span></label>
        <label class="settings-switch"><input v-model="form.alert_sound_enabled" type="checkbox" /><span><strong>Sonido de alerta</strong><small>Activa señal sonora en el espacio de trabajo.</small></span></label>
      </div>
      <div class="row g-3">
        <div class="col-12 col-lg-4"><BaseInput v-model="form.alert_threshold" label="Umbral general" name="alert_threshold" type="number" :error="fieldError('alert_threshold')" required /></div>
        <div class="col-12 col-lg-4"><BaseInput v-model="form.sensor_update_interval" label="Intervalo de sensores (ms)" name="sensor_update_interval" type="number" :error="fieldError('sensor_update_interval')" required /></div>
        <div class="col-12 col-lg-4"><BaseInput v-model="form.danger_email_rate_limit_seconds" label="Límite de correo crítico (s)" name="danger_email_rate_limit_seconds" type="number" :error="fieldError('danger_email_rate_limit_seconds')" required /></div>
      </div>
      <div class="settings-form-actions"><BaseButton type="submit" :loading="saving"><LabIcon name="save" /> Guardar alertas</BaseButton></div>
    </form>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { getAlertConfig, updateAlertConfig } from '@/api/config';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue'; import BaseButton from '@/components/base/BaseButton.vue'; import BaseInput from '@/components/base/BaseInput.vue'; import LoadingSpinner from '@/components/base/LoadingSpinner.vue'; import LabIcon from '@/components/dashboard/lab/LabIcon.vue'; import { validationMessage } from '@/utils/formatters';
const form = reactive({ mail_enabled: false, alert_sound_enabled: false, alert_threshold: 0, sensor_update_interval: 1000, danger_email_rate_limit_seconds: 0 });
const loading = ref(true); const saving = ref(false); const error = ref(''); const success = ref(''); const errors = ref({});
function fieldError(field) { return validationMessage(errors.value, field); }
function payload() { return { mail_enabled: Boolean(form.mail_enabled), alert_sound_enabled: Boolean(form.alert_sound_enabled), alert_threshold: Number(form.alert_threshold), sensor_update_interval: Number(form.sensor_update_interval), danger_email_rate_limit_seconds: Number(form.danger_email_rate_limit_seconds) }; }
async function load() { loading.value = true; error.value = ''; try { Object.assign(form, unwrapData(await getAlertConfig()) || {}); } catch (requestError) { error.value = getApiErrorMessage(requestError, 'No se pudo cargar la configuración de alertas.'); } finally { loading.value = false; } }
async function save() { saving.value = true; error.value = ''; success.value = ''; errors.value = {}; try { const response = await updateAlertConfig(payload()); Object.assign(form, unwrapData(response) || {}); success.value = response.data?.message || 'Configuración de alertas actualizada.'; } catch (requestError) { errors.value = getValidationErrors(requestError); error.value = getApiErrorMessage(requestError, 'No se pudo guardar la configuración de alertas.'); } finally { saving.value = false; } }
onMounted(load);
</script>
