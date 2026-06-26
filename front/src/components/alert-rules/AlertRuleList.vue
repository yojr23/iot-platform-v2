<template>
  <div class="content-panel">
    <div v-if="rules.length === 0" class="text-center text-muted py-5">
      No hay reglas de alerta para mostrar.
    </div>

    <div v-else class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Alcance</th>
            <th>Severidad</th>
            <th>Umbrales</th>
            <th>Mensaje</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rule in rules" :key="rule.id">
            <td class="fw-semibold">{{ rule.name || `Regla ${rule.id}` }}</td>
            <td>
              <div>{{ rule.sensor?.name || rule.device?.name || rule.sensor_type?.name || 'Global' }}</div>
              <div class="small text-muted">{{ rule.sensor_type?.unit || rule.sensor_type?.name || '-' }}</div>
            </td>
            <td>
              <span class="badge" :class="severityClass(rule.severity)">
                {{ severityLabel(rule.severity) }}
              </span>
            </td>
            <td>
              <span v-if="rule.min_value !== null">Min {{ formatNumber(rule.min_value) }}</span>
              <span v-if="rule.min_value !== null && rule.max_value !== null"> · </span>
              <span v-if="rule.max_value !== null">Max {{ formatNumber(rule.max_value) }}</span>
            </td>
            <td>{{ rule.message }}</td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" type="button" @click="$emit('edit', rule)">Editar</button>
                <button class="btn btn-outline-danger" type="button" :disabled="deletingId === rule.id" @click="$emit('delete', rule)">
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
import { formatNumber, severityLabel } from '@/utils/formatters';

defineProps({
  rules: {
    type: Array,
    default: () => []
  },
  deletingId: {
    type: [Number, String, null],
    default: null
  }
});

defineEmits(['edit', 'delete']);

function severityClass(severity) {
  return severity === 'danger' ? 'text-bg-danger' : severity === 'warning' ? 'text-bg-warning' : 'text-bg-secondary';
}
</script>
