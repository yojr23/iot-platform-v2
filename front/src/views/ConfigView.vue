<template>
  <section>
    <div class="config-hero mb-4">
      <div>
        <p class="section-kicker mb-2">Panel administrativo</p>
        <h1 class="h3 mb-1">Configuracion del sistema</h1>
        <p class="mb-0">Alertas, correo, parametros generales y accesos de administracion.</p>
      </div>
      <RouterLink class="btn btn-light" to="/dashboard">Volver al dashboard</RouterLink>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <LoadingSpinner v-if="loading" label="Cargando configuracion..." />

    <div v-if="!loading" class="row g-3">
      <div class="col-12">
        <div class="content-panel p-3">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
            <div>
              <h2 class="h5 mb-1">Configuracion general</h2>
              <p class="text-muted small mb-0">Valores visibles del frontend y referencia de entorno.</p>
            </div>
            <span class="badge rounded-pill" :class="isAdmin ? 'text-bg-primary' : 'text-bg-secondary'">
              {{ isAdmin ? 'Administrador' : 'Solo lectura' }}
            </span>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <BaseInput
                :model-value="publicConfig.app_name || 'iot-platform-v2'"
                label="Nombre de la aplicacion"
                name="app_name"
                disabled
              />
            </div>
            <div class="col-12 col-lg-6">
              <BaseInput
                :model-value="publicConfig.app_url || appUrl"
                label="URL de la aplicacion"
                name="app_url"
                disabled
              />
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="content-panel p-3">
          <h2 class="h5 mb-3">Alertas</h2>

          <form @submit.prevent="saveAlertConfig">
            <div class="form-check form-switch mb-3">
              <input id="mail_enabled" v-model="alertForm.mail_enabled" class="form-check-input" type="checkbox" />
              <label class="form-check-label" for="mail_enabled">Notificaciones por email</label>
            </div>

            <div class="form-check form-switch mb-3">
              <input id="alert_sound_enabled" v-model="alertForm.alert_sound_enabled" class="form-check-input" type="checkbox" />
              <label class="form-check-label" for="alert_sound_enabled">Sonido de alerta</label>
            </div>

            <BaseInput
              v-model="alertForm.alert_threshold"
              label="Umbral general de alertas"
              name="alert_threshold"
              type="number"
              :error="fieldError(alertErrors, 'alert_threshold')"
              required
            />

            <BaseInput
              v-model="alertForm.sensor_update_interval"
              label="Intervalo de actualizacion de sensores (ms)"
              name="sensor_update_interval"
              type="number"
              :error="fieldError(alertErrors, 'sensor_update_interval')"
              required
            />

            <BaseInput
              v-model="alertForm.danger_email_rate_limit_seconds"
              label="Rate limit email critico (segundos)"
              name="danger_email_rate_limit_seconds"
              type="number"
              :error="fieldError(alertErrors, 'danger_email_rate_limit_seconds')"
              required
            />

            <BaseButton type="submit" :loading="savingAlerts">Guardar alertas</BaseButton>
          </form>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="content-panel p-3">
          <h2 class="h5 mb-3">SMTP / Email</h2>

          <div class="alert alert-light border small">
            Contrasena configurada:
            <strong>{{ emailForm.password_configured ? 'si' : 'no' }}</strong>.
          </div>

          <form @submit.prevent="saveEmailConfig">
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <BaseInput v-model="emailForm.mail_mailer" label="Mailer" name="mail_mailer" :error="fieldError(emailErrors, 'mail_mailer')" required />
              </div>
              <div class="col-12 col-lg-6">
                <BaseInput v-model="emailForm.mail_encryption" label="Encriptacion" name="mail_encryption" :error="fieldError(emailErrors, 'mail_encryption')" required />
              </div>
              <div class="col-12 col-lg-8">
                <BaseInput v-model="emailForm.mail_host" label="Host" name="mail_host" :error="fieldError(emailErrors, 'mail_host')" required />
              </div>
              <div class="col-12 col-lg-4">
                <BaseInput v-model="emailForm.mail_port" label="Puerto" name="mail_port" type="number" :error="fieldError(emailErrors, 'mail_port')" required />
              </div>
              <div class="col-12">
                <BaseInput v-model="emailForm.mail_username" label="Usuario SMTP" name="mail_username" type="email" :error="fieldError(emailErrors, 'mail_username')" required />
              </div>
              <div class="col-12">
                <BaseInput
                  v-model="emailForm.mail_password"
                  label="Nueva contrasena SMTP"
                  name="mail_password"
                  type="password"
                  autocomplete="new-password"
                  :error="fieldError(emailErrors, 'mail_password')"
                />
              </div>
              <div class="col-12 col-lg-6">
                <BaseInput v-model="emailForm.mail_from_address" label="Remitente" name="mail_from_address" type="email" :error="fieldError(emailErrors, 'mail_from_address')" required />
              </div>
              <div class="col-12 col-lg-6">
                <BaseInput v-model="emailForm.mail_from_name" label="Nombre remitente" name="mail_from_name" :error="fieldError(emailErrors, 'mail_from_name')" required />
              </div>
              <div class="col-12">
                <BaseInput v-model="emailForm.mail_to" label="Destino de alertas" name="mail_to" type="email" :error="fieldError(emailErrors, 'mail_to')" required />
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <BaseButton type="submit" :loading="savingEmail">Guardar email</BaseButton>
            </div>
          </form>

          <hr />

          <form class="d-flex flex-column flex-lg-row gap-2" @submit.prevent="sendTestEmail">
            <input v-model="testEmail" class="form-control" type="email" placeholder="correo@ejemplo.com" required />
            <BaseButton type="submit" variant="outline-primary" :loading="testingEmail">Probar email</BaseButton>
          </form>
        </div>
      </div>

      <div class="col-12">
        <div class="content-panel p-3">
          <h2 class="h5 mb-3">Acciones de administracion</h2>
          <div class="row g-3">
            <div v-for="action in adminActions" :key="action.label" class="col-12 col-md-6 col-xl-4">
              <RouterLink class="admin-action" :to="action.href">
                <span class="admin-action__label">{{ action.label }}</span>
                <span class="admin-action__description">{{ action.description }}</span>
              </RouterLink>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="content-panel p-3">
          <h2 class="h5 mb-3">Configuracion publica expuesta al frontend</h2>
          <pre class="bg-light border rounded p-3 mb-0"><code>{{ publicConfig }}</code></pre>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

import {
  getAlertConfig,
  getEmailConfig,
  getPublicConfig,
  testEmailConfig,
  updateAlertConfig,
  updateEmailConfig
} from '@/api/config';
import { getApiErrorMessage, getValidationErrors, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { useAuthStore } from '@/stores/auth';
import { validationMessage } from '@/utils/formatters';

const authStore = useAuthStore();
const publicConfig = ref({});
const loading = ref(false);
const savingAlerts = ref(false);
const savingEmail = ref(false);
const testingEmail = ref(false);
const error = ref('');
const success = ref('');
const alertErrors = ref({});
const emailErrors = ref({});
const testEmail = ref('');
const appUrl = window.location.origin;
const isAdmin = computed(() => Boolean(authStore.user?.is_admin));
const adminActions = computed(() => [
  {
    label: 'Gestionar configuracion de email',
    description: 'Servidor SMTP, credenciales, remitente y correo destino.',
    href: '/config'
  },
  {
    label: 'Gestionar tipos de sensores',
    description: 'Crear y ajustar catalogos de sensores usados en reglas.',
    href: '/sensor-types'
  },
  {
    label: 'Gestionar tipos de dispositivos',
    description: 'Mantener familias de equipos IoT disponibles.',
    href: '/device-types'
  },
  {
    label: 'Gestionar laboratorios',
    description: 'Crear areas, laboratorios y lineas de proceso.',
    href: '/labs'
  },
  {
    label: 'Configurar reglas de alerta',
    description: 'Definir umbrales por sensor, dispositivo y severidad.',
    href: '/alert-rules'
  },
  {
    label: 'Gestionar roles de usuarios',
    description: 'Asignar o revocar permisos de administrador.',
    href: '/users'
  },
  {
    label: 'Metricas tecnicas',
    description: 'Consultar rendimiento y telemetria interna del sistema.',
    href: '/metrics'
  }
]);

const alertForm = reactive({
  mail_enabled: false,
  alert_sound_enabled: false,
  alert_threshold: 0,
  sensor_update_interval: 1000,
  danger_email_rate_limit_seconds: 0
});

const emailForm = reactive({
  mail_mailer: 'smtp',
  mail_host: '',
  mail_port: 587,
  mail_username: '',
  mail_password: '',
  mail_encryption: 'tls',
  mail_from_address: '',
  mail_from_name: '',
  mail_to: '',
  password_configured: false
});

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const publicResponse = await getPublicConfig();
    publicConfig.value = unwrapData(publicResponse) || {};

    try {
      const alertResponse = await getAlertConfig();
      Object.assign(alertForm, unwrapData(alertResponse) || {});
    } catch (configError) {
      error.value = getApiErrorMessage(configError, 'La configuracion de alertas requiere permisos adicionales.');
    }

    try {
      const emailResponse = await getEmailConfig();
      Object.assign(emailForm, unwrapData(emailResponse) || {}, { mail_password: '' });
      testEmail.value = emailForm.mail_to || '';
    } catch (emailError) {
      error.value = getApiErrorMessage(emailError, 'La configuracion email requiere permisos adicionales.');
    }
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo cargar la configuracion publica.');
  } finally {
    loading.value = false;
  }
}

function alertPayload() {
  return {
    mail_enabled: Boolean(alertForm.mail_enabled),
    alert_sound_enabled: Boolean(alertForm.alert_sound_enabled),
    alert_threshold: Number(alertForm.alert_threshold),
    sensor_update_interval: Number(alertForm.sensor_update_interval),
    danger_email_rate_limit_seconds: Number(alertForm.danger_email_rate_limit_seconds)
  };
}

function emailPayload() {
  const payload = {
    mail_mailer: emailForm.mail_mailer,
    mail_host: emailForm.mail_host,
    mail_port: Number(emailForm.mail_port),
    mail_username: emailForm.mail_username,
    mail_encryption: emailForm.mail_encryption,
    mail_from_address: emailForm.mail_from_address,
    mail_from_name: emailForm.mail_from_name,
    mail_to: emailForm.mail_to
  };

  if (emailForm.mail_password) {
    payload.mail_password = emailForm.mail_password;
  }

  return payload;
}

async function saveAlertConfig() {
  savingAlerts.value = true;
  error.value = '';
  success.value = '';
  alertErrors.value = {};

  try {
    const response = await updateAlertConfig(alertPayload());
    Object.assign(alertForm, unwrapData(response) || {});
    success.value = response.data?.message || 'Configuracion de alertas actualizada.';
  } catch (requestError) {
    alertErrors.value = getValidationErrors(requestError);
    error.value = getApiErrorMessage(requestError, 'No se pudo guardar la configuracion de alertas.');
  } finally {
    savingAlerts.value = false;
  }
}

async function saveEmailConfig() {
  savingEmail.value = true;
  error.value = '';
  success.value = '';
  emailErrors.value = {};

  try {
    const response = await updateEmailConfig(emailPayload());
    Object.assign(emailForm, unwrapData(response) || {}, { mail_password: '' });
    success.value = response.data?.message || 'Configuracion de email actualizada.';
  } catch (requestError) {
    emailErrors.value = getValidationErrors(requestError);
    error.value = getApiErrorMessage(requestError, 'No se pudo guardar la configuracion de email.');
  } finally {
    savingEmail.value = false;
  }
}

async function sendTestEmail() {
  testingEmail.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await testEmailConfig({ test_email: testEmail.value });
    success.value = response.data?.message || 'Email de prueba enviado.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No fue posible enviar el email de prueba.');
  } finally {
    testingEmail.value = false;
  }
}

function fieldError(errors, field) {
  return validationMessage(errors, field);
}

onMounted(load);
</script>
