<template>
  <AuthLayout title="Recuperar acceso">
    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />

    <form @submit.prevent="submit">
      <BaseInput
        v-model="email"
        label="Email"
        name="email"
        type="email"
        autocomplete="email"
        required
      />

      <BaseButton class="w-100" type="submit" :loading="loading">Enviar enlace</BaseButton>
    </form>

    <RouterLink class="small d-inline-block mt-3" to="/login">Volver al login</RouterLink>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';

import { forgotPassword } from '@/api/auth';
import { getApiErrorMessage } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const email = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

async function submit() {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    await forgotPassword({ email: email.value });
    success.value = 'Si el correo existe, recibiras instrucciones para recuperar el acceso.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo enviar el enlace.');
  } finally {
    loading.value = false;
  }
}
</script>
