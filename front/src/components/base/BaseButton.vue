<template>
  <button
    :type="type"
    class="btn"
    :class="`btn-${variant}`"
    :disabled="disabled || loading"
  >
    <span
      v-if="loading"
      class="spinner-border spinner-border-sm me-2"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>

<script setup>
defineProps({
  type: {
    type: String,
    default: 'button'
  },
  variant: {
    type: String,
    default: 'primary'
  },
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  }
});
</script>
