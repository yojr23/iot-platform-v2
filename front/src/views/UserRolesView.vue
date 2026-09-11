<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">Usuarios y roles</h1>
        <p class="lab-resource-description">Gestiona permisos administrativos y responsabilidades del equipo.</p>
      </div>
      <div class="lab-resource-actions">
        <button class="btn btn-outline-secondary lab-action" type="button" :disabled="loading" @click="load"><I name="refresh" />Actualizar</button>
      </div>
    </div>

    <BaseAlert v-if="error" variant="danger" :message="error" />
    <BaseAlert v-if="success" variant="success" :message="success" />
    <LoadingSpinner v-if="loading" label="Cargando usuarios..." />

    <div v-if="!loading" class="content-panel">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Departamento</th>
              <th>Registro</th>
              <th class="text-end">Accion</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td class="fw-semibold">{{ user.name }}</td>
              <td>{{ user.email }}</td>
              <td>
                <span class="badge" :class="user.is_admin ? 'text-bg-primary' : 'text-bg-secondary'">
                  {{ user.role }}
                </span>
              </td>
              <td>{{ user.department || '-' }}</td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td class="text-end">
                <button
                  class="btn btn-sm lab-action"
                  :class="user.is_admin ? 'btn-outline-secondary' : 'btn-outline-primary'"
                  type="button"
                  :disabled="savingId === user.id"
                  @click="toggleRole(user)"
                >
                  <I :name="user.is_admin ? 'lock' : 'user'" />{{ user.is_admin ? 'Quitar admin' : 'Hacer admin' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import { getUsers, updateUserRole } from '@/api/users';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import I from '@/components/dashboard/lab/LabIcon.vue';
import { asArray, formatDate } from '@/utils/formatters';

const users = ref([]);
const loading = ref(false);
const savingId = ref(null);
const error = ref('');
const success = ref('');

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await getUsers();
    users.value = asArray(unwrapData(response));
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar los usuarios.');
  } finally {
    loading.value = false;
  }
}

async function toggleRole(user) {
  savingId.value = user.id;
  error.value = '';
  success.value = '';

  try {
    const response = await updateUserRole(user.id, { is_admin: !user.is_admin });
    success.value = response.data?.message || 'Rol actualizado.';
    await load();
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudo actualizar el rol.');
  } finally {
    savingId.value = null;
  }
}

onMounted(load);
</script>
