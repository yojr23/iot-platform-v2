<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">Mi cuenta</h1>
        <p class="lab-resource-description">Consulta los datos de tu sesion, rol y registro.</p>
      </div>
      <div class="lab-resource-actions">
        <button class="btn btn-outline-secondary lab-action" type="button" :disabled="loading" @click="load"><I name="refresh" />Actualizar</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando perfil..." />

    <div v-if="!loading" class="content-panel p-3">
      <div class="lab-profile-identity">
        <span class="lab-profile-avatar"><I name="user" /></span>
        <div>
          <h2>{{ profile.name || 'Mi perfil' }}</h2>
          <p>{{ profile.email || 'Sin correo registrado' }}</p>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <p class="text-muted small mb-1">Nombre</p>
          <p class="fw-semibold mb-0">{{ profile.name || '-' }}</p>
        </div>
        <div class="col-12 col-lg-6">
          <p class="text-muted small mb-1">Email</p>
          <p class="fw-semibold mb-0">{{ profile.email || '-' }}</p>
        </div>
        <div class="col-12 col-lg-4">
          <p class="text-muted small mb-1">Rol</p>
          <span class="badge" :class="profile.is_admin ? 'text-bg-primary' : 'text-bg-secondary'">
            {{ profile.role || '-' }}
          </span>
        </div>
        <div class="col-12 col-lg-4">
          <p class="text-muted small mb-1">Departamento</p>
          <p class="mb-0">{{ profile.department || '-' }}</p>
        </div>
        <div class="col-12 col-lg-4">
          <p class="text-muted small mb-1">Registro</p>
          <p class="mb-0">{{ formatDate(profile.created_at) }}</p>
        </div>
        <div class="col-12 col-lg-4">
          <p class="text-muted small mb-1">Ultima actualizacion</p>
          <p class="mb-0">{{ formatDate(profile.updated_at) }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import { getProfile } from '@/api/profile';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import I from '@/components/dashboard/lab/LabIcon.vue';
import { formatDate } from '@/utils/formatters';

const profile = ref({});
const loading = ref(false);
const error = ref('');

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await getProfile();
    profile.value = unwrapData(response) || {};
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo cargar el perfil.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
