<template>
  <BaseModal
    :show="show"
    :title="title"
    subtitle="Los cambios se guardan mediante la API."
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
