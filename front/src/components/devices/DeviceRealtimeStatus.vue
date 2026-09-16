<template>
  <span
    class="badge"
    :class="status.className"
    role="status"
    aria-live="polite"
    :data-realtime-mode="status.mode"
  >
    {{ status.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

import { useDeviceStatusesStore } from '@/stores/deviceStatuses';

const deviceStatuses = useDeviceStatusesStore();

const statuses = {
  live: { mode: 'live', label: 'Tiempo real', className: 'text-bg-success' },
  recovering: { mode: 'recovering', label: 'Sincronizando', className: 'text-bg-warning' },
  stale: { mode: 'stale', label: 'Datos posiblemente desactualizados', className: 'text-bg-warning' },
  disconnected: { mode: 'disconnected', label: 'Desconectado', className: 'text-bg-secondary' }
};

const status = computed(() => statuses[deviceStatuses.realtimeStatus.mode] || statuses.disconnected);
</script>
