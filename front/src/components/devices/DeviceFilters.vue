<template>
  <div class="row g-2 lab-resource-filters">
    <div class="col-12">
      <label class="lab-resource-search">
        <I name="search" />
        <input
          class="form-control"
          :value="search"
          type="search"
          aria-label="Buscar dispositivos"
          placeholder="Buscar por nombre, serial, tipo o laboratorio"
          @input="$emit('update:search', $event.target.value)"
        />
      </label>
    </div>
  </div>
</template>

<script setup>
import I from '@/components/dashboard/lab/LabIcon.vue';

defineProps({
  search: {
    type: String,
    default: ''
  }
});

defineEmits(['update:search']);
</script>
