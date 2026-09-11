<template>
  <section class="lab-resource-page settings-page">
    <div class="lab-toolbar lab-resource-toolbar settings-toolbar">
      <div><RouterLink class="settings-back" to="/config"><LabIcon name="left" /> Volver a Configuración</RouterLink><h1 class="lab-resource-title">Correo</h1><p class="lab-resource-description">Configura el envío SMTP y verifica la entrega antes de depender de las alertas.</p></div>
      <span class="settings-section-badge is-info"><LabIcon name="mail" /> SMTP</span>
    </div>
    <BaseAlert v-if="error" variant="danger" :message="error" /><BaseAlert v-if="success" variant="success" :message="success" />
    <LoadingSpinner v-if="loading" label="Cargando correo..." />
    <template v-else>
      <form class="settings-form-panel" @submit.prevent="save">
        <div class="settings-form-heading"><h2>Conexión SMTP</h2><p>La contraseña existente nunca se muestra. Déjala vacía para conservarla.</p></div>
        <div class="settings-password-state" :class="email.password_configured ? 'is-success' : 'is-warning'"><LabIcon :name="email.password_configured ? 'check' : 'alert'" /><span>Contraseña SMTP: <strong>{{ email.password_configured ? 'configurada' : 'pendiente' }}</strong></span></div>
        <div class="row g-3">
          <div class="col-12 col-lg-6"><BaseInput v-model="email.mail_mailer" label="Mailer" name="mail_mailer" :error="fieldError('mail_mailer')" required /></div>
          <div class="col-12 col-lg-6"><BaseInput v-model="email.mail_encryption" label="Encriptación" name="mail_encryption" :error="fieldError('mail_encryption')" required /></div>
          <div class="col-12 col-lg-8"><BaseInput v-model="email.mail_host" label="Host" name="mail_host" :error="fieldError('mail_host')" required /></div>
          <div class="col-12 col-lg-4"><BaseInput v-model="email.mail_port" label="Puerto" name="mail_port" type="number" :error="fieldError('mail_port')" required /></div>
          <div class="col-12"><BaseInput v-model="email.mail_username" label="Usuario SMTP" name="mail_username" type="email" :error="fieldError('mail_username')" required /></div>
          <div class="col-12"><BaseInput v-model="email.mail_password" label="Nueva contraseña SMTP" name="mail_password" type="password" autocomplete="new-password" :error="fieldError('mail_password')" /></div>
          <div class="col-12 col-lg-6"><BaseInput v-model="email.mail_from_address" label="Remitente" name="mail_from_address" type="email" :error="fieldError('mail_from_address')" required /></div>
          <div class="col-12 col-lg-6"><BaseInput v-model="email.mail_from_name" label="Nombre del remitente" name="mail_from_name" :error="fieldError('mail_from_name')" required /></div>
          <div class="col-12"><BaseInput v-model="email.mail_to" label="Destino de alertas" name="mail_to" type="email" :error="fieldError('mail_to')" required /></div>
        </div>
        <div class="settings-form-actions"><BaseButton type="submit" :loading="saving"><LabIcon name="save" /> Guardar correo</BaseButton></div>
      </form>
      <form class="settings-test-panel" data-test-email-form @submit.prevent="sendTest">
        <div><p class="settings-eyebrow">Verificación</p><h2>Enviar correo de prueba</h2><p>Confirma que el servidor SMTP puede entregar mensajes a una dirección real.</p></div>
        <div class="settings-test-actions"><input v-model="testEmail" class="form-control" type="email" aria-label="Correo de prueba" required /><BaseButton type="submit" variant="outline-primary" :loading="testing"><LabIcon name="mail" /> Probar envío</BaseButton></div>
      </form>
    </template>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { getEmailConfig, testEmailConfig, updateEmailConfig } from '@/api/config';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue'; import BaseButton from '@/components/base/BaseButton.vue'; import BaseInput from '@/components/base/BaseInput.vue'; import LoadingSpinner from '@/components/base/LoadingSpinner.vue'; import LabIcon from '@/components/dashboard/lab/LabIcon.vue'; import { validationMessage } from '@/utils/formatters';
const email = reactive({ mail_mailer: 'smtp', mail_host: '', mail_port: 587, mail_username: '', mail_password: '', mail_encryption: 'tls', mail_from_address: '', mail_from_name: '', mail_to: '', password_configured: false }); const testEmail = ref(''); const loading = ref(true); const saving = ref(false); const testing = ref(false); const error = ref(''); const success = ref(''); const errors = ref({});
function fieldError(field) { return validationMessage(errors.value, field); }
function payload() { const result = { mail_mailer: email.mail_mailer, mail_host: email.mail_host, mail_port: Number(email.mail_port), mail_username: email.mail_username, mail_encryption: email.mail_encryption, mail_from_address: email.mail_from_address, mail_from_name: email.mail_from_name, mail_to: email.mail_to }; if (email.mail_password) result.mail_password = email.mail_password; return result; }
async function load() { loading.value = true; error.value = ''; try { Object.assign(email, unwrapData(await getEmailConfig()) || {}, { mail_password: '' }); testEmail.value = email.mail_to || ''; } catch (requestError) { error.value = getApiErrorMessage(requestError, 'No se pudo cargar la configuración de correo.'); } finally { loading.value = false; } }
async function save() { saving.value = true; error.value = ''; success.value = ''; errors.value = {}; try { const response = await updateEmailConfig(payload()); Object.assign(email, unwrapData(response) || {}, { mail_password: '' }); success.value = response.data?.message || 'Configuración de correo actualizada.'; } catch (requestError) { errors.value = getValidationErrors(requestError); error.value = getApiErrorMessage(requestError, 'No se pudo guardar la configuración de correo.'); } finally { saving.value = false; } }
async function sendTest() { testing.value = true; error.value = ''; success.value = ''; try { const response = await testEmailConfig({ test_email: testEmail.value }); success.value = response.data?.message || 'Correo de prueba enviado.'; } catch (requestError) { error.value = getApiErrorMessage(requestError, 'No fue posible enviar el correo de prueba.'); } finally { testing.value = false; } }
onMounted(load);
</script>
