<template>
  <BaseModal
    :show="show"
    :title="title"
    subtitle="Define las condiciones que deben generar una alerta."
    @close="$emit('close')"
  >
    <slot />
  </BaseModal>
</template>

<script setup>
import BaseModal from '@/components/base/BaseModal.vue';

defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Regla de alerta'
  }
});

defineEmits(['close']);
</script>
