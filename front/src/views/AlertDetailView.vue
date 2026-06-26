<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <p class="section-kicker text-primary mb-2">Alertas</p>
        <h1 class="h3 mb-1">Alerta {{ id }}</h1>
        <p class="text-muted mb-0">Contexto completo de sensor, dispositivo, valor y estado.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary" to="/alerts">Volver</RouterLink>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <LoadingSpinner v-if="loading" label="Cargando alerta..." />

    <div v-if="!loading" class="row g-3">
      <div class="col-12 col-xl-5">
        <div class="content-panel p-3 h-100">
          <h2 class="h5">Estado</h2>
          <dl class="mb-0">
            <dt>Mensaje</dt>
            <dd>{{ alert.alert_rule?.message || alert.alert_rule?.name || '-' }}</dd>
            <dt>Gravedad</dt>
            <dd><span class="badge text-bg-danger">{{ severityLabel(alert.alert_rule?.severity) }}</span></dd>
            <dt>Fecha</dt>
            <dd>{{ formatDate(alert.created_at) }}</dd>
            <dt>Resolucion</dt>
            <dd>{{ alert.resolved ? `Resuelta ${formatDate(alert.resolved_at)}` : 'Activa' }}</dd>
          </dl>
          <button v-if="!alert.resolved" class="btn btn-primary mt-3" type="button" :disabled="resolving" @click="resolve">
            Resolver alerta
          </button>
        </div>
      </div>

      <div class="col-12 col-xl-7">
        <div class="content-panel p-3 h-100">
          <h2 class="h5">Contexto</h2>
          <dl class="mb-0">
            <dt>Sensor</dt>
            <dd>{{ alert.sensor?.name || '-' }}</dd>
            <dt>Tipo</dt>
            <dd>{{ alert.sensor?.sensor_type?.name || '-' }}</dd>
            <dt>Valor detectado</dt>
            <dd>{{ formatNumber(alert.sensor_reading?.value) }} {{ alert.sensor?.sensor_type?.unit || '' }}</dd>
            <dt>Dispositivo</dt>
            <dd>{{ alert.device?.name || '-' }}</dd>
            <dt>Laboratorio</dt>
            <dd>{{ alert.device?.lab?.name || '-' }}</dd>
            <dt>Rango regla</dt>
            <dd>{{ formatNumber(alert.alert_rule?.min_value) }} - {{ formatNumber(alert.alert_rule?.max_value) }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import { getAlert, resolveAlert } from '@/api/alerts';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { formatDate, formatNumber, severityLabel } from '@/utils/formatters';

const props = defineProps({
  id: {
    type: String,
    required: true
  }
});

const alert = ref({});
const loading = ref(false);
const resolving = ref(false);
const error = ref('');
const success = ref('');

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await getAlert(props.id);
    alert.value = unwrapData(response) || {};
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo cargar la alerta.');
  } finally {
    loading.value = false;
  }
}

async function resolve() {
  resolving.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await resolveAlert(props.id);
    alert.value = unwrapData(response) || {};
    success.value = 'Alerta resuelta correctamente.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo resolver la alerta.');
  } finally {
    resolving.value = false;
  }
}

onMounted(load);
</script>
