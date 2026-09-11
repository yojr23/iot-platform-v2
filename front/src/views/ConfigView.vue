<template>
  <section class="lab-resource-page settings-page">
    <div class="lab-toolbar lab-resource-toolbar settings-toolbar">
      <div>
        <p class="settings-eyebrow"><LabIcon name="settings" /> Administración</p>
        <h1 class="lab-resource-title">Configuración</h1>
        <p class="lab-resource-description">Consulta el estado del sistema y ajusta cada área desde una vista enfocada.</p>
      </div>
      <div class="lab-resource-actions">
        <span class="settings-role"><LabIcon name="shield" /> Administrador</span>
        <RouterLink class="btn btn-outline-secondary" to="/dashboard">Volver al dashboard</RouterLink>
      </div>
    </div>

    <LoadingSpinner v-if="loading" label="Cargando configuración..." />
    <template v-else>
      <BaseAlert v-if="loadNotice" variant="warning" :message="loadNotice" />

      <section class="settings-summary" aria-labelledby="settings-summary-title">
        <div class="settings-summary-heading">
          <div><p class="settings-eyebrow">Resumen operativo</p><h2 id="settings-summary-title">Estado de configuración</h2></div>
          <span class="settings-summary-name">{{ general.app_name || 'SINOA' }}</span>
        </div>
        <div class="settings-status-grid">
          <div v-for="item in statusItems" :key="item.label" class="settings-status" :class="`is-${item.tone}`">
            <span class="settings-status-icon"><LabIcon :name="item.icon" /></span>
            <div><span>{{ item.label }}</span><strong>{{ item.value }}</strong></div>
          </div>
        </div>
      </section>

      <section class="settings-list-panel" aria-labelledby="settings-areas-title">
        <div class="settings-list-heading">
          <div><p class="settings-eyebrow">Áreas del sistema</p><h2 id="settings-areas-title">Elige qué quieres ajustar</h2></div>
          <span class="settings-list-caption">Cambios guardados por área</span>
        </div>
        <div class="settings-section-list">
          <RouterLink v-for="section in sections" :key="section.to" :to="section.to" class="settings-section-row">
            <span class="settings-section-icon" :class="`is-${section.tone}`"><LabIcon :name="section.icon" /></span>
            <span class="settings-section-copy"><strong>{{ section.title }}</strong><small>{{ section.description }}</small></span>
            <span class="settings-section-state" :class="`is-${section.tone}`">{{ section.status }}</span>
            <span class="settings-section-open">Abrir <LabIcon name="right" /></span>
          </RouterLink>
        </div>
      </section>

      <section class="settings-admin-panel" aria-labelledby="settings-admin-title">
        <div><p class="settings-eyebrow">Gestión administrativa</p><h2 id="settings-admin-title">Recursos relacionados</h2><p>Estas acciones se mantienen fuera de la barra lateral para que la navegación operativa siga despejada.</p></div>
        <div class="settings-admin-links">
          <RouterLink v-for="action in adminActions" :key="action.to" :to="action.to"><LabIcon :name="action.icon" /><span>{{ action.label }}</span><LabIcon name="right" /></RouterLink>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { getAlertConfig, getEmailConfig, getGeneralConfig, getRuntimeConfig, getSystemInfo } from '@/api/config';
import { unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import LabIcon from '@/components/dashboard/lab/LabIcon.vue';

const loading = ref(true);
const loadNotice = ref('');
const general = ref({});
const alerts = ref(null);
const email = ref(null);
const diagnostics = ref(null);

const statusItems = computed(() => [
  { label: 'Aplicación', value: general.value.app_name ? 'Configurada' : 'Requiere revisión', icon: 'settings', tone: general.value.app_name ? 'success' : 'warning' },
  { label: 'Alertas', value: alerts.value ? (alerts.value.mail_enabled ? 'Correo activo' : 'Sin correo') : 'No disponible', icon: 'bell', tone: alerts.value ? (alerts.value.mail_enabled ? 'success' : 'warning') : 'neutral' },
  { label: 'Correo', value: email.value ? (email.value.password_configured ? 'Listo para usar' : 'Falta contraseña') : 'No disponible', icon: 'mail', tone: email.value ? (email.value.password_configured ? 'success' : 'warning') : 'neutral' },
  { label: 'Diagnóstico', value: diagnostics.value?.environment || 'No disponible', icon: 'server', tone: diagnostics.value ? 'info' : 'neutral' }
]);
const sections = computed(() => [
  { to: '/config/general', icon: 'settings', title: 'General', description: 'Nombre visible y URL de la aplicación.', status: general.value.app_name ? 'Configurado' : 'Revisar', tone: general.value.app_name ? 'success' : 'warning' },
  { to: '/config/alerts', icon: 'bell', title: 'Alertas', description: 'Correo, sonido, umbrales e intervalos de actualización.', status: alerts.value ? (alerts.value.mail_enabled ? 'Activo' : 'Revisar') : 'No disponible', tone: alerts.value?.mail_enabled ? 'success' : alerts.value ? 'warning' : 'neutral' },
  { to: '/config/email', icon: 'mail', title: 'Correo', description: 'Servidor SMTP, remitente, destino y envío de prueba.', status: email.value?.password_configured ? 'Listo' : email.value ? 'Revisar' : 'No disponible', tone: email.value?.password_configured ? 'success' : email.value ? 'warning' : 'neutral' },
  { to: '/config/diagnostics', icon: 'server', title: 'Diagnóstico', description: 'Versiones y entorno del servicio en modo de solo lectura.', status: diagnostics.value ? 'Disponible' : 'No disponible', tone: diagnostics.value ? 'info' : 'neutral' }
]);
const adminActions = [
  { to: '/sensor-types', label: 'Tipos de sensores', icon: 'sensor' }, { to: '/device-types', label: 'Tipos de dispositivos', icon: 'device' }, { to: '/labs', label: 'Laboratorios', icon: 'database' }, { to: '/alert-rules', label: 'Reglas de alerta', icon: 'alert' }, { to: '/users', label: 'Usuarios y roles', icon: 'user' }
];

async function load() {
  loading.value = true; loadNotice.value = '';
  const results = await Promise.allSettled([getRuntimeConfig(), getGeneralConfig(), getAlertConfig(), getEmailConfig(), getSystemInfo()]);
  const [runtimeResult, generalResult, alertResult, emailResult, diagnosticsResult] = results;
  const runtime = runtimeResult.status === 'fulfilled' ? unwrapData(runtimeResult.value) || {} : {};
  general.value = generalResult.status === 'fulfilled' ? unwrapData(generalResult.value) || runtime : runtime;
  alerts.value = alertResult.status === 'fulfilled' ? unwrapData(alertResult.value) || {} : null;
  email.value = emailResult.status === 'fulfilled' ? unwrapData(emailResult.value) || {} : null;
  diagnostics.value = diagnosticsResult.status === 'fulfilled' ? unwrapData(diagnosticsResult.value) || {} : null;
  if (results.some((result) => result.status === 'rejected')) loadNotice.value = 'Algunos estados no están disponibles ahora. Puedes abrir cada área para revisarla o reintentar más tarde.';
  loading.value = false;
}
onMounted(load);
</script>
