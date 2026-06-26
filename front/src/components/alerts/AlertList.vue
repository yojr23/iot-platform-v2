<template>
  <div class="content-panel">
    <div v-if="alerts.length === 0" class="text-center text-muted py-5">
      No hay alertas para mostrar.
    </div>

    <div v-else class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Alerta</th>
            <th>Severidad</th>
            <th>Valor</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th class="text-end">Accion</th>
          </tr>
        </thead>
        <tbody>
          <AlertItem
            v-for="alert in alerts"
            :key="alert.id"
            :alert="alert"
            :resolving="resolvingId === alert.id"
            @resolve="$emit('resolve', $event)"
          />
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import AlertItem from './AlertItem.vue';

defineProps({
  alerts: {
    type: Array,
    default: () => []
  },
  resolvingId: {
    type: [Number, String, null],
    default: null
  }
});

defineEmits(['resolve']);
</script>
