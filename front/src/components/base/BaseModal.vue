<template>
  <div v-if="show" class="phase-modal-backdrop">
    <section
      ref="dialogEl"
      class="phase-modal"
      :class="contentClass"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      tabindex="-1"
      @keydown="onKeydown"
    >
      <header class="d-flex justify-content-between align-items-start gap-3 border-bottom p-3">
        <div>
          <h2 :id="titleId" class="h5 mb-1">{{ title }}</h2>
          <p v-if="subtitle" class="text-muted small mb-0">{{ subtitle }}</p>
        </div>
        <button class="btn-close" type="button" aria-label="Cerrar" @click="close" />
      </header>

      <div class="p-3">
        <slot />
      </div>
    </section>
  </div>
</template>

<script setup>
import { nextTick, ref, useId, watch } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  contentClass: {
    type: String,
    default: 'bg-white shadow-lg'
  }
});

const emit = defineEmits(['close']);

const titleId = useId();
const dialogEl = ref(null);
let previouslyFocused = null;

function focusableElements() {
  if (!dialogEl.value) {
    return [];
  }

  return Array.from(
    dialogEl.value.querySelectorAll(
      'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  ).filter((el) => el.offsetParent !== null);
}

function close() {
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
    return;
  }

  if (event.key !== 'Tab') {
    return;
  }

  const focusable = focusableElements();
  if (focusable.length === 0) {
    event.preventDefault();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

watch(
  () => props.show,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocused = document.activeElement;
      await nextTick();
      (focusableElements()[0] || dialogEl.value)?.focus();
      return;
    }

    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
      previouslyFocused.focus();
    }
    previouslyFocused = null;
  }
);
</script>
