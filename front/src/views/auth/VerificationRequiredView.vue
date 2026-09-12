<template>
  <AuthLayout title="Verifica tu correo">
    <BaseAlert variant="success" :message="message" />

    <p class="small text-muted mt-3 mb-0">
      Cuando completes la verificacion, podras iniciar sesion.
      <RouterLink to="/login">Ir al login</RouterLink>
    </p>
  </AuthLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import BaseAlert from '@/components/base/BaseAlert.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const route = useRoute();
const message = computed(() => route.query.email
  ? `Enviamos un enlace de verificacion a ${route.query.email}.`
  : 'Enviamos un enlace de verificacion a tu correo.');
</script>
