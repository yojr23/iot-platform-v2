<template>
  <div v-if="message || $slots.default" class="alert" :class="`alert-${variant}`" role="alert">
    <slot>{{ message }}</slot>
  </div>
</template>

<script setup>
defineProps({
  message: {
    type: String,
    default: ''
  },
  variant: {
    type: String,
    default: 'info'
  }
});
</script>
