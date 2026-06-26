<template>
  <AuthLayout title="Verificacion de email">
    <LoadingSpinner v-if="loading" label="Verificando email..." />
    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />

    <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
      <RouterLink class="btn btn-primary" to="/login">Ir al login</RouterLink>
      <BaseButton
        v-if="authStore.isAuthenticated"
        type="button"
        variant="outline-primary"
        :loading="resending"
        @click="resend"
      >
        Reenviar correo
      </BaseButton>
    </div>
  </AuthLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import { resendVerification, verifyEmail } from '@/api/auth';
import { getApiErrorMessage } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const authStore = useAuthStore();
const loading = ref(true);
const resending = ref(false);
const error = ref('');
const success = ref('');

onMounted(async () => {
  try {
    await verifyEmail(route.params.id, route.params.hash, route.query);
    success.value = 'Email verificado correctamente.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo verificar el email.');
  } finally {
    loading.value = false;
  }
});

async function resend() {
  resending.value = true;
  error.value = '';
  success.value = '';

  try {
    await resendVerification();
    success.value = 'Correo de verificacion reenviado.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo reenviar el correo de verificacion.');
  } finally {
    resending.value = false;
  }
}
</script>
