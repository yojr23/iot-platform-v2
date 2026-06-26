<template>
  <section class="content-panel p-3 h-100">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">Dispositivos</h2>
        <p class="text-muted small mb-0">Estado operativo y sensores asociados.</p>
      </div>
      <RouterLink v-if="authStore.isAuthenticated" class="btn btn-sm btn-outline-primary" to="/devices">Ver todos</RouterLink>
    </div>

    <BaseAlert v-if="error" variant="warning" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando dispositivos..." />

    <div v-if="!loading && visibleDevices.length === 0" class="text-muted small py-3">
      No hay dispositivos registrados.
    </div>

    <div v-if="visibleDevices.length" class="list-group list-group-flush">
      <component
        :is="authStore.isAuthenticated ? 'RouterLink' : 'div'"
        v-for="device in visibleDevices"
        :key="device.id"
        class="list-group-item list-group-item-action px-0"
        :to="authStore.isAuthenticated ? '/devices' : undefined"
      >
        <div class="d-flex justify-content-between gap-3">
          <div>
            <p class="fw-semibold mb-1">{{ device.name || `Dispositivo ${device.id}` }}</p>
            <p class="small text-muted mb-0">
              {{ device.device_type?.name || 'Tipo no definido' }} · {{ device.sensors?.length || 0 }} sensores
            </p>
          </div>
          <span class="badge align-self-start" :class="device.status ? 'text-bg-success' : 'text-bg-secondary'">
            {{ device.status ? 'Activo' : 'Inactivo' }}
          </span>
        </div>
      </component>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import { getDevices } from '@/api/devices';
import { getApiErrorMessage } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import { useAuthStore } from '@/stores/auth';
import { paginatedItems } from '@/utils/formatters';

const props = defineProps({
  devices: {
    type: Array,
    default: null
  }
});

const authStore = useAuthStore();
const devices = ref([]);
const loading = ref(false);
const error = ref('');
const visibleDevices = computed(() => props.devices || devices.value);

async function load() {
  if (props.devices) {
    devices.value = props.devices;
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const response = await getDevices({ per_page: 5 });
    devices.value = paginatedItems(response);
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar dispositivos.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
