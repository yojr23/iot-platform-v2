<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <p class="section-kicker text-primary mb-2">Observabilidad</p>
        <h1 class="h3 mb-1">Metricas del sistema</h1>
        <p class="text-muted mb-0">Estado de la plataforma IoT y rendimiento operativo.</p>
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
            <p class="h4 mb-0" :class="metric.class || ''">{{ metric.value }}</p>
            <p v-if="metric.sub" class="text-muted small mb-0">{{ metric.sub }}</p>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <div class="content-panel">
            <div class="p-3 border-bottom">
              <h2 class="h5 mb-1">Dispositivos</h2>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Metrica</th>
                    <th class="text-end">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Total dispositivos</td>
                    <td class="text-end fw-semibold">{{ snapshot.total_devices }}</td>
                  </tr>
                  <tr>
                    <td>En linea</td>
                    <td class="text-end fw-semibold text-success">{{ snapshot.online_devices }}</td>
                  </tr>
                  <tr>
                    <td>Fuera de linea</td>
                    <td class="text-end fw-semibold" :class="snapshot.offline_devices > 0 ? 'text-danger' : ''">{{ snapshot.offline_devices }}</td>
                  </tr>
                  <tr>
                    <td>Total sensores</td>
                    <td class="text-end fw-semibold">{{ snapshot.total_sensors }}</td>
                  </tr>
                  <tr>
                    <td>Lecturas hoy</td>
                    <td class="text-end fw-semibold">{{ snapshot.readings_today }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="content-panel">
            <div class="p-3 border-bottom">
              <h2 class="h5 mb-1">Alertas y reglas</h2>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Metrica</th>
                    <th class="text-end">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Alertas activas</td>
                    <td class="text-end fw-semibold" :class="snapshot.active_alerts > 0 ? 'text-danger' : 'text-success'">{{ snapshot.active_alerts }}</td>
                  </tr>
                  <tr>
                    <td>Reglas totales</td>
                    <td class="text-end fw-semibold">{{ snapshot.total_alert_rules }}</td>
                  </tr>
                  <tr>
                    <td>Reglas habilitadas</td>
                    <td class="text-end fw-semibold">{{ snapshot.enabled_rules }}</td>
                  </tr>
                  <tr>
                    <td>Laboratorios</td>
                    <td class="text-end fw-semibold">{{ snapshot.total_labs }}</td>
                  </tr>
                  <tr>
                    <td>Uptime</td>
                    <td class="text-end fw-semibold" :class="snapshot.uptime_percent >= 99 ? 'text-success' : snapshot.uptime_percent >= 95 ? 'text-warning' : 'text-danger'">{{ snapshot.uptime_percent }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import { getMetrics } from '@/api/metrics';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';

const loading = ref(false);
const error = ref('');
const snapshot = ref({});

const metricCards = computed(() => [
  { label: 'Dispositivos', value: snapshot.value.total_devices || 0, sub: `${snapshot.value.online_devices || 0} en linea · ${snapshot.value.offline_devices || 0} fuera`, class: '' },
  { label: 'Sensores', value: snapshot.value.total_sensors || 0, sub: `${snapshot.value.readings_today || 0} lecturas hoy` },
  { label: 'Alertas activas', value: snapshot.value.active_alerts || 0, sub: `${snapshot.value.total_alert_rules || 0} reglas (${snapshot.value.enabled_rules || 0} activas)`, class: (snapshot.value.active_alerts || 0) > 0 ? 'text-danger' : 'text-success' },
  { label: 'Uptime', value: `${snapshot.value.uptime_percent || 0}%`, sub: `${snapshot.value.total_labs || 0} laboratorios`, class: (snapshot.value.uptime_percent || 0) >= 99 ? 'text-success' : (snapshot.value.uptime_percent || 0) >= 95 ? 'text-warning' : 'text-danger' }
]);

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await getMetrics();
    snapshot.value = unwrapData(response) || {};
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar las metricas.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
