<template>
  <div class="content-panel">
    <div v-if="sensors.length === 0" class="text-center text-muted py-5">
      No hay sensores para mostrar.
    </div>

    <div v-else class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Dispositivo</th>
            <th>Laboratorio</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sensor in sensors" :key="sensor.id">
            <td class="fw-semibold">{{ sensor.name || `Sensor ${sensor.id}` }}</td>
            <td>{{ sensor.sensor_type?.name || '-' }}</td>
            <td>{{ sensor.device?.name || '-' }}</td>
            <td>{{ sensor.device?.lab?.name || '-' }}</td>
            <td>
              <span class="badge" :class="sensor.status ? 'text-bg-success' : 'text-bg-secondary'">
                {{ statusLabel(sensor.status) }}
              </span>
            </td>
            <td class="text-end">
              <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de sensor">
                <RouterLink class="btn btn-outline-primary" :to="`/sensors/${sensor.id}`">Ver</RouterLink>
                <button class="btn btn-outline-info" type="button" @click="$emit('export', sensor)">Exportar</button>
                <button
                  v-if="authStore.user?.is_admin"
                  class="btn btn-outline-warning"
                  type="button"
                  @click="$emit('edit', sensor)"
                >
                  Editar
                </button>
                <button
                  v-if="authStore.user?.is_admin"
                  class="btn btn-outline-danger"
                  type="button"
                  @click="$emit('delete', sensor)"
                >
                  Eliminar
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
import { statusLabel } from '@/utils/formatters';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

defineProps({
  sensors: {
    type: Array,
    default: () => []
  }
});

defineEmits(['edit', 'delete', 'export']);
</script>
