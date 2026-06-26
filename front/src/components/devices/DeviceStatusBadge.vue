<template>
  <span class="badge" :class="active ? 'text-bg-success' : 'text-bg-secondary'">
    {{ active ? 'Activo' : 'Inactivo' }}
  </span>
</template>

<script setup>
defineProps({
  active: {
    type: Boolean,
    default: false
  }
});
</script>
