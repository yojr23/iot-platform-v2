<template>
  <div class="row g-2 mb-3">
    <div class="col-12 col-lg-8">
      <input
        class="form-control"
        :value="search"
        type="search"
        placeholder="Buscar por nombre, tipo o dispositivo"
        @input="$emit('update:search', $event.target.value)"
      />
    </div>
    <div class="col-12 col-lg-4">
      <select class="form-select" :value="status" @change="$emit('update:status', $event.target.value)">
        <option value="all">Todos los estados</option>
        <option value="active">Activos</option>
        <option value="inactive">Inactivos</option>
      </select>
    </div>
  </div>
</template>

<script setup>
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
