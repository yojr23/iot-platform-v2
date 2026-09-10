<template>
  <div class="row g-2 lab-resource-filters">
    <div class="col-12">
      <input
        class="form-control"
        :value="search"
        type="search"
        aria-label="Buscar dispositivos"
        placeholder="Buscar por nombre, serial, tipo o laboratorio"
        @input="$emit('update:search', $event.target.value)"
      />
    </div>
  </div>
</template>

<script setup>
defineProps({
  search: {
    type: String,
    default: ''
  }
});

defineEmits(['update:search']);
</script>
