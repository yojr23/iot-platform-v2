<template>
  <div v-if="show" class="phase-modal-backdrop">
    <section class="phase-modal bg-white shadow-lg">
      <header class="d-flex justify-content-between align-items-start gap-3 border-bottom p-3">
        <div>
          <h2 class="h5 mb-1">{{ title }}</h2>
          <p class="text-muted small mb-0">Los cambios se guardan mediante la API.</p>
        </div>
        <button class="btn-close" type="button" aria-label="Cerrar" @click="$emit('close')" />
      </header>

      <div class="p-3">
        <slot />
      </div>
    </section>
  </div>
</template>

<script setup>
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
