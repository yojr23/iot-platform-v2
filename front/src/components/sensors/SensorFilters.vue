<template>
  <div class="row g-2 lab-resource-filters">
    <div class="col-12 col-lg-8">
      <label class="lab-resource-search">
        <I name="search" />
        <input
          class="form-control"
          :value="search"
          type="search"
          aria-label="Buscar sensores"
          placeholder="Buscar por nombre, tipo o dispositivo"
          @input="$emit('update:search', $event.target.value)"
        />
      </label>
    </div>
    <div class="col-12 col-lg-4">
      <select class="form-select" aria-label="Estado del sensor" :value="status" @change="$emit('update:status', $event.target.value)">
        <option value="all">Todos los estados</option>
        <option value="active">Activos</option>
        <option value="inactive">Inactivos</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import I from '@/components/dashboard/lab/LabIcon.vue';

defineProps({
  search: {
    type: String,
    default: ''
  },
  status: {
    type: String,
    default: 'all'
  }
});

defineEmits(['update:search', 'update:status']);
</script>
