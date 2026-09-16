<template>
  <BaseModal
    :show="show"
    title="Credencial de ingesta"
    subtitle="Guárdala antes de cerrar esta ventana."
    content-class="content-panel"
    @close="$emit('close')"
  >
    <p class="mb-3">Esta clave no se mostrará de nuevo.</p>
    <label class="form-label" for="one_time_device_api_key">API key del dispositivo</label>
    <div class="input-group">
      <input
        id="one_time_device_api_key"
        class="form-control font-monospace"
        :value="apiKey"
        readonly
      />
      <button class="btn btn-outline-secondary" type="button" @click="copyApiKey">Copiar</button>
    </div>
    <p v-if="copied" class="text-success small mt-2 mb-0">Copiado al portapapeles.</p>
    <p v-if="copyError" class="text-danger small mt-2 mb-0">No se pudo copiar la clave. Cópiala manualmente antes de cerrar.</p>
    <div class="d-flex justify-content-end mt-3">
      <button class="btn btn-primary" type="button" @click="$emit('close')">He guardado la clave</button>
    </div>
  </BaseModal>
</template>

<script setup>
import { ref, watch } from 'vue';

import BaseModal from '@/components/base/BaseModal.vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  apiKey: {
    type: String,
    default: ''
  }
});

defineEmits(['close']);

const copied = ref(false);
const copyError = ref(false);

async function copyApiKey() {
  if (!props.apiKey) {
    return;
  }

  copied.value = false;
  copyError.value = false;
  try {
    await navigator.clipboard.writeText(props.apiKey);
    copied.value = true;
  } catch {
    copyError.value = true;
  }
}

watch(
  () => props.show,
  (isOpen) => {
    if (!isOpen) {
      copied.value = false;
      copyError.value = false;
    }
  }
);
</script>
