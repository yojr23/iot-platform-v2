<template>
  <section class="content-panel p-3 h-100">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">Dispositivos</h2>
        <p class="text-muted small mb-0">Estado operativo y sensores asociados.</p>
      </div>
      <RouterLink v-if="authStore.isAuthenticated" class="btn btn-sm btn-outline-primary" to="/devices">Ver todos</RouterLink>
    </div>

    <div v-if="visibleDevices.length === 0" class="text-muted small py-3">
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
import { computed } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { useDeviceStatusesStore } from '@/stores/deviceStatuses';

// Gate 8.5: presentation-only. Device metadata always comes from the parent (dashboard's own
// load()); status is overlaid from the shared realtime projection instead of an independent
// getDevices() poll on mount.
const props = defineProps({
  devices: {
    type: Array,
    default: () => []
  }
});

const authStore = useAuthStore();
const deviceStatuses = useDeviceStatusesStore();

const visibleDevices = computed(() => props.devices.map((device) => {
  const projected = deviceStatuses.statusFor(device.id);
  return projected ? { ...device, status: projected.status, is_active: projected.is_active } : device;
}));
</script>
