<template>
  <div class="d-flex align-items-center gap-2 text-muted-strong" :class="{ 'justify-content-center': centered }">
    <span class="spinner-border spinner-border-sm" aria-hidden="true" />
    <span>{{ label }}</span>
  </div>
</template>

<script setup>
defineProps({
  label: {
    type: String,
    default: 'Cargando...'
  },
  centered: {
    type: Boolean,
    default: false
  }
});
</script>
