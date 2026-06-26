<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <p class="section-kicker text-primary mb-2">Observabilidad</p>
        <h1 class="h3 mb-1">Metricas tecnicas</h1>
        <p class="text-muted mb-0">Rendimiento API y errores en la ventana reciente.</p>
      </div>
      <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="load">Actualizar</button>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando metricas..." />

    <template v-if="!loading">
      <div class="row g-3 mb-3">
        <div v-for="metric in metricCards" :key="metric.label" class="col-12 col-md-6 col-xl-3">
          <div class="content-panel p-3 h-100">
            <p class="text-muted small mb-1">{{ metric.label }}</p>
            <p class="h4 mb-0">{{ metric.value }}</p>
          </div>
        </div>
      </div>

      <div class="content-panel">
        <div class="p-3 border-bottom">
          <h2 class="h5 mb-1">Serie por minuto</h2>
          <p class="text-muted small mb-0">Generado: {{ formatDate(generatedAt) }}</p>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Bucket</th>
                <th>Requests</th>
                <th>Errores</th>
                <th>Latencia promedio</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="point in snapshot.series || []" :key="point.bucket">
                <td>{{ point.bucket }}</td>
                <td>{{ point.requests }}</td>
                <td>{{ point.errors }}</td>
                <td>{{ point.avg_latency_ms }} ms</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import { getMetrics } from '@/api/metrics';
import { getApiErrorMessage } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { formatDate, formatNumber } from '@/utils/formatters';

const loading = ref(false);
const error = ref('');
const snapshot = ref({});
const generatedAt = ref('');

const metricCards = computed(() => [
  { label: 'Requests', value: formatNumber(snapshot.value.requests_total) },
  { label: 'Errores', value: formatNumber(snapshot.value.errors_total) },
  { label: 'Error rate', value: `${formatNumber(snapshot.value.error_rate_percent)}%` },
  { label: 'Latencia promedio', value: `${formatNumber(snapshot.value.avg_latency_ms)} ms` }
]);

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await getMetrics();
    snapshot.value = response.data?.snapshot || {};
    generatedAt.value = response.data?.generated_at || '';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar las metricas.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
