<template>
  <AuthLayout title="Iniciar sesion">
    <BaseAlert v-if="authStore.error" variant="danger" :message="authStore.error" />

    <form @submit.prevent="submit">
      <BaseInput
        v-model="form.email"
        label="Email"
        name="email"
        type="email"
        autocomplete="email"
        required
      />

      <BaseInput
        v-model="form.password"
        label="Contrasena"
        name="password"
        type="password"
        autocomplete="current-password"
        required
      />

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
          <input id="remember" v-model="form.remember" class="form-check-input" type="checkbox" />
          <label class="form-check-label" for="remember">Recordarme</label>
        </div>

        <RouterLink class="small" to="/forgot-password">Recuperar acceso</RouterLink>
      </div>

      <BaseButton class="w-100" type="submit" :loading="authStore.loading">
        Entrar
      </BaseButton>
    </form>

    <p class="small text-muted mt-3 mb-0">
      Sin cuenta?
      <RouterLink to="/register">Crear usuario</RouterLink>
    </p>
  </AuthLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
  email: '',
  password: '',
  remember: false
});

async function submit() {
  try {
    await authStore.login(form);
    router.push(route.query.redirect || { name: 'dashboard' });
  } catch {
    // El store ya expone el error visible en pantalla.
  }
}
</script>
