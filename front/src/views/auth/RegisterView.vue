<template>
  <AuthLayout title="Crear usuario">
    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />

    <form @submit.prevent="submit">
      <BaseInput v-model="form.name" label="Nombre" name="name" autocomplete="name" required />
      <BaseInput v-model="form.email" label="Email" name="email" type="email" autocomplete="email" required />
      <BaseInput
        v-model="form.password"
        label="Contrasena"
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

      <BaseButton class="w-100" type="submit" :loading="loading">Crear cuenta</BaseButton>
    </form>

    <p class="small text-muted mt-3 mb-0">
      Ya tienes cuenta?
      <RouterLink to="/login">Iniciar sesion</RouterLink>
    </p>
  </AuthLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';

import { register } from '@/api/auth';
import { getApiErrorMessage } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const loading = ref(false);
const error = ref('');
const success = ref('');

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
});

async function submit() {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    await register(form);
    success.value = 'Usuario creado. Revisa tu correo para verificar la cuenta.';
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo crear el usuario.');
  } finally {
    loading.value = false;
  }
}
</script>
