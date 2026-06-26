<template>
  <tr>
    <td>
      <div class="fw-semibold">{{ alert.alert_rule?.message || alert.alert_rule?.name || `Alerta ${alert.id}` }}</div>
      <div class="small text-muted">
        {{ alert.sensor?.name || 'Sensor no disponible' }} · {{ alert.device?.name || 'Dispositivo no disponible' }}
      </div>
    </td>
    <td>
      <span class="badge" :class="severityClass">
        {{ severityLabel(alert.alert_rule?.severity) }}
      </span>
    </td>
    <td>{{ formatNumber(alert.sensor_reading?.value) }}</td>
    <td>
      <span class="badge" :class="alert.resolved ? 'text-bg-secondary' : 'text-bg-danger'">
        {{ alert.resolved ? 'Resuelta' : 'Activa' }}
      </span>
    </td>
    <td>{{ formatDate(alert.created_at) }}</td>
    <td class="text-end">
      <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de alerta">
        <RouterLink class="btn btn-outline-primary" :to="`/alerts/${alert.id}`">Ver</RouterLink>
        <button
          class="btn btn-outline-success"
          type="button"
          :disabled="alert.resolved || resolving"
          @click="$emit('resolve', alert)"
        >
          Resolver
        </button>
      </div>
    </td>
  </tr>
</template>

<script setup>
import { computed } from 'vue';

import { formatDate, formatNumber, severityLabel } from '@/utils/formatters';

const props = defineProps({
  alert: {
    type: Object,
    required: true
  },
  resolving: {
    type: Boolean,
    default: false
  }
});

defineEmits(['resolve']);

const severityClass = computed(() => {
  const severity = props.alert.alert_rule?.severity;
  return severity === 'danger' ? 'text-bg-danger' : severity === 'warning' ? 'text-bg-warning' : 'text-bg-secondary';
});
</script>
