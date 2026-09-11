<template>
  <div class="content-panel">
    <div v-if="devices.length === 0" class="text-center text-muted py-5">
      No hay dispositivos para mostrar.
    </div>

    <div v-else class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Dispositivo</th>
            <th>Ubicacion</th>
            <th>Tipo</th>
            <th>Laboratorio</th>
            <th>Firmware</th>
            <th>Sensores</th>
            <th>Ultima comunicacion</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="device in devices" :key="device.id">
            <td>
              <div class="fw-semibold">{{ device.name || `Dispositivo ${device.id}` }}</div>
              <div class="small text-muted">{{ device.serial_number || device.ip_address || '-' }}</div>
            </td>
            <td>
              <div>{{ device.location || '-' }}</div>
              <div v-if="device.description" class="small text-muted text-truncate" style="max-width: 180px" :title="device.description">{{ device.description }}</div>
            </td>
            <td>{{ device.device_type?.name || '-' }}</td>
            <td>{{ device.lab?.name || '-' }}</td>
            <td><span class="badge text-bg-light border">{{ device.firmware_version || '-' }}</span></td>
            <td>{{ device.sensors?.length || 0 }}</td>
            <td>{{ formatDate(device.last_seen || device.last_communication) }}</td>
            <td>
              <button
                v-if="authStore.user?.is_admin"
                class="btn btn-sm lab-status-control"
                :class="device.status && device.is_active ? 'btn-success' : 'btn-outline-secondary'"
                type="button"
                :disabled="updatingId === device.id"
                @click="$emit('toggle-status', device)"
              >
                <I :name="device.status && device.is_active ? 'check' : 'device'" />
                {{ device.status && device.is_active ? 'Activo' : 'Inactivo' }}
              </button>
              <DeviceStatusBadge v-else :active="Boolean(device.status && device.is_active)" />
            </td>
            <td class="text-end">
              <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de dispositivo">
                <RouterLink class="btn btn-outline-primary lab-action" :to="`/devices/${device.id}`"><I name="eye" />Ver</RouterLink>
                <button
                  v-if="authStore.user?.is_admin"
                  class="btn btn-outline-secondary lab-action"
                  type="button"
                  @click="$emit('edit', device)"
                >
                  <I name="edit" />Editar
                </button>
                <button
                  v-if="authStore.user?.is_admin"
                  class="btn btn-outline-danger lab-action"
                  type="button"
                  @click="$emit('delete', device)"
                >
                  <I name="trash" />Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import DeviceStatusBadge from './DeviceStatusBadge.vue';
import I from '@/components/dashboard/lab/LabIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { formatDate } from '@/utils/formatters';

const authStore = useAuthStore();

defineProps({
  devices: {
    type: Array,
    default: () => []
  },
  updatingId: {
    type: [Number, String],
    default: null
  }
});

defineEmits(['toggle-status', 'edit', 'delete']);
</script>
