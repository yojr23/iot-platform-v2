<template>
  <section>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="h3 mb-1">{{ device?.name || `Dispositivo ${id}` }}</h1>
        <p class="text-muted mb-0">Detalle, catalogos asociados, red y sensores conectados.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary" to="/devices">Volver</RouterLink>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando dispositivo..." />

    <div v-if="!loading" class="row g-3">
      <div class="col-12 col-xl-4">
        <div class="content-panel p-3">
          <h2 class="h5">Datos</h2>
          <dl class="mb-0">
            <dt>Serial</dt>
            <dd>{{ device?.serial_number || '-' }}</dd>
            <dt>Tipo</dt>
            <dd>{{ device?.device_type?.name || '-' }}</dd>
            <dt>Laboratorio</dt>
            <dd>{{ device?.lab?.name || '-' }}</dd>
            <dt>IP</dt>
            <dd>{{ device?.ip_address || '-' }}</dd>
            <dt>MAC</dt>
            <dd>{{ device?.mac_address || '-' }}</dd>
            <dt>Ultima comunicacion</dt>
            <dd>{{ formatDate(device?.last_communication) }}</dd>
            <dt>Estado</dt>
            <dd>
              <span class="badge" :class="device?.status && device?.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                {{ device?.status && device?.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </dd>
          </dl>
        </div>
      </div>

      <div class="col-12 col-xl-8">
        <div class="content-panel">
          <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <div>
              <h2 class="h5 mb-1">Sensores asociados</h2>
              <p class="text-muted small mb-0">Sensores instalados en este dispositivo.</p>
            </div>
            <span class="badge text-bg-light border">{{ sensors.length }}</span>
          </div>

          <div v-if="sensors.length === 0" class="text-center text-muted py-5">
            No hay sensores asociados.
          </div>
          <div v-else class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Sensor</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sensor in sensors" :key="sensor.id">
                  <td class="fw-semibold">{{ sensor.name }}</td>
                  <td>{{ sensor.sensor_type?.name || '-' }}</td>
                  <td>
                    <span class="badge" :class="sensor.status ? 'text-bg-success' : 'text-bg-secondary'">
                      {{ sensor.status ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                  <td class="text-end">
                    <RouterLink class="btn btn-sm btn-outline-primary" :to="`/sensors/${sensor.id}`">Ver sensor</RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import { getDevice, getDeviceSensors } from '@/api/devices';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { asArray, formatDate } from '@/utils/formatters';

const props = defineProps({
  id: {
    type: String,
    required: true
  }
});

const device = ref(null);
const sensors = ref([]);
const loading = ref(false);
const error = ref('');

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const [deviceResponse, sensorsResponse] = await Promise.all([
      getDevice(props.id),
      getDeviceSensors(props.id)
    ]);
    device.value = unwrapData(deviceResponse);
    sensors.value = asArray(unwrapData(sensorsResponse));
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo cargar el dispositivo.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
