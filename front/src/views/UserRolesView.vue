<template>
  <section class="lab-resource-page">
    <div class="lab-toolbar lab-resource-toolbar">
      <div>
        <h1 class="lab-resource-title">Usuarios y roles</h1>
        <p class="lab-resource-description">Gestiona permisos administrativos y responsabilidades del equipo.</p>
      </div>
      <div class="lab-resource-actions">
        <button class="btn btn-outline-secondary lab-action" type="button" :disabled="loading" @click="load">
          <I name="refresh" />Actualizar
        </button>
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
              <th>Permisos</th>
              <th>Registro</th>
              <th class="text-end">Accion</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td class="fw-semibold">{{ user.name }}</td>
              <td>{{ user.email }}</td>
              <td>
                <span class="badge" :class="getRoleBadgeClass(user.role?.code)">
                  {{ user.role?.name || 'Sin rol' }}
                </span>
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <span
                    v-for="perm in (user.permissions || []).slice(0, 3)"
                    :key="perm"
                    class="badge text-bg-light"
                  >
                    {{ perm }}
                  </span>
                  <span v-if="(user.permissions || []).length > 3" class="badge text-bg-light">
                    +{{ user.permissions.length - 3 }} mas
                  </span>
                </div>
              </td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td class="text-end">
                <div class="dropdown">
                  <button
                    class="btn btn-sm btn-outline-secondary dropdown-toggle"
                    type="button"
                    :disabled="savingId === user.id || !canManageUser(user)"
                    data-bs-toggle="dropdown"
                  >
                    <I name="gear" />
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li v-for="role in assignableRoles" :key="role.code">
                      <button
                        v-if="role.code !== user.role?.code && canAssignRole(role.code)"
                        class="dropdown-item"
                        type="button"
                        @click="changeRole(user, role.code)"
                      >
                        Cambiar a {{ role.name }}
                      </button>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';

import { getUsers, updateUserRole, getRoles } from '@/api/users';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import BaseAlert from '@/components/base/BaseAlert.vue';
import LoadingSpinner from '@/components/base/LoadingSpinner.vue';
import I from '@/components/dashboard/lab/LabIcon.vue';
import { asArray, formatDate } from '@/utils/formatters';

const authStore = useAuthStore();
const users = ref([]);
const roles = ref([]);
const loading = ref(false);
const savingId = ref(null);
const error = ref('');
const success = ref('');

const assignableRoles = computed(() => {
  return roles.value.filter(r => r.assignable && r.code !== 'guest');
});

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const [usersResponse, rolesResponse] = await Promise.all([
      getUsers(),
      getRoles()
    ]);
    users.value = asArray(unwrapData(usersResponse));
    roles.value = asArray(unwrapData(rolesResponse));
  } catch (requestError) {
    error.value = getApiErrorMessage(requestError, 'No se pudieron cargar los usuarios.');
  } finally {
    loading.value = false;
  }
}

function canManageUser(user) {
  return authStore.can('user.role.assign');
}

function canAssignRole(roleCode) {
  return authStore.can('user.role.assign');
}

function getRoleBadgeClass(roleCode) {
  switch (roleCode) {
    case 'superadmin': return 'text-bg-danger';
    case 'admin': return 'text-bg-primary';
    case 'user': return 'text-bg-secondary';
    default: return 'text-bg-light';
  }
}

async function changeRole(user, newRoleCode) {
  savingId.value = user.id;
  error.value = '';
  success.value = '';

  try {
    const response = await updateUserRole(user.id, { role_code: newRoleCode });
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
