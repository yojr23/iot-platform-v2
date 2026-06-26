<template>
  <AuthLayout title="Restablecer contrasena">
    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />

    <form @submit.prevent="submit">
      <BaseInput v-model="form.email" label="Email" name="email" type="email" autocomplete="email" required />
      <BaseInput
        v-model="form.token"
        label="Token"
        name="token"
        autocomplete="one-time-code"
        required
      />
      <BaseInput
        v-model="form.password"
        label="Nueva contrasena"
        name="password"
        type="password"
        autocomplete="new-password"
        required
      />
      <BaseInput
        v-model="form.password_confirmation"
        label="Confirmar contrasena"
        name="password_confirmation"
        type="password"
        autocomplete="new-password"
        required
      />

      <BaseButton class="w-100" type="submit" :loading="loading">Actualizar</BaseButton>
    </form>
  </AuthLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';

import { resetPassword } from '@/api/auth';
import { getApiErrorMessage } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const route = useRoute();
const loading = ref(false);
const error = ref('');
const success = ref('');

const form = reactive({
  email: '',
  token: '',
  password: '',
  password_confirmation: ''
});

onMounted(() => {
  form.email = route.query.email || '';
  form.token = route.query.token || '';
});

async function submit() {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    await resetPassword(form);
    success.value = 'Contrasena actualizada. Ya puedes iniciar sesion.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo restablecer la contrasena.');
  } finally {
    loading.value = false;
  }
}
</script>
