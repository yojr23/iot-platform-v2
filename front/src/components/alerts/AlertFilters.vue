<template>
  <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
    <div class="btn-group" role="group" aria-label="Filtro de alertas">
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        class="btn btn-sm"
        :class="modelValue === option.value ? 'btn-primary' : 'btn-outline-primary'"
        @click="$emit('update:modelValue', option.value)"
      >
        {{ option.label }}
      </button>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="loading" @click="$emit('refresh')">
        Actualizar
      </button>
      <button class="btn btn-sm btn-danger" type="button" :disabled="loading" @click="$emit('resolve-all')">
        Resolver todas
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: {
    type: String,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:modelValue', 'refresh', 'resolve-all']);

const options = [
  { label: 'Todas', value: 'all' },
  { label: 'No resueltas', value: 'unresolved' },
  { label: 'Activas', value: 'active' }
];
</script>
