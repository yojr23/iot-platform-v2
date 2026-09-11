<template>
  <nav class="navbar navbar-expand-lg app-navbar border-bottom">
    <div class="container-fluid px-3 px-lg-4">
      <RouterLink class="navbar-brand fw-semibold" to="/dashboard">
        IoT Platform
      </RouterLink>

      <button
        class="navbar-toggler"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#mainNavbar"
        aria-controls="mainNavbar"
        aria-expanded="false"
        aria-label="Abrir navegacion"
      >
        <span class="navbar-toggler-icon" />
      </button>

      <div id="mainNavbar" class="collapse navbar-collapse">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li v-for="item in navItems" :key="item.to" class="nav-item">
            <RouterLink class="nav-link" active-class="active fw-semibold" :to="item.to">
              {{ item.label }}
            </RouterLink>
          </li>
        </ul>

        <div class="d-flex align-items-center gap-3">
          <span
            v-if="authStore.isAuthenticated"
            class="badge rounded-pill d-none d-lg-inline-flex"
            :class="realtimeStatusClass"
          >
            {{ realtimeStatusLabel }}
          </span>

          <RouterLink v-if="authStore.isAuthenticated" class="btn btn-outline-secondary btn-sm position-relative" to="/alerts">
            Alertas
            <span
              v-if="alertsStore.unresolvedCount > 0"
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"
            >
              {{ alertsStore.unresolvedCount }}
            </span>
          </RouterLink>
          <!-- Guests get no alert count/banner identity (pre-Stage-6 guest isolation). -->

          <RouterLink v-if="authStore.isAuthenticated" class="small text-muted" to="/profile">
            {{ authStore.user?.name }}
          </RouterLink>

          <button
            v-if="authStore.isAuthenticated"
            class="btn btn-primary btn-sm"
            type="button"
            :disabled="authStore.loading"
            @click="handleLogout"
          >
            Salir
          </button>

          <RouterLink v-else class="btn btn-primary btn-sm" to="/login">
            Ingresar
          </RouterLink>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';

import { useAlertsStore } from '@/stores/alerts';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const alertsStore = useAlertsStore();

const laboratoryItems = [
  { label: 'Sensores', to: '/sensors' },
  { label: 'Dispositivos', to: '/devices' },
  { label: 'Alertas', to: '/alerts' }
];

const adminItems = [
  { label: 'Métricas', to: '/metrics' },
  { label: 'Configuración', to: '/config' }
];

const navItems = computed(() => {
  const publicItems = [
    { label: 'Dashboard', to: '/dashboard' }
  ];

  if (!authStore.isAuthenticated) {
    return publicItems;
  }

  return [
    ...publicItems,
    ...laboratoryItems,
    ...(authStore.user?.is_admin ? adminItems : [])
  ];
});

const realtimeStatusLabel = computed(() => {
  const realtimeStatus = alertsStore.realtimeStatus;

  if (realtimeStatus.connected) {
    return 'Tiempo real';
  }

  if (realtimeStatus.mode === 'recovering') {
    return 'Recuperando';
  }

  if (realtimeStatus.enabled) {
    return 'Conectando';
  }

  return realtimeStatus.mode === 'stale' ? 'Estado desactualizado' : 'Sin conexion';
});

const realtimeStatusClass = computed(() => {
  const realtimeStatus = alertsStore.realtimeStatus;

  if (realtimeStatus.connected) {
    return 'text-bg-success';
  }

  if (realtimeStatus.enabled) {
    return 'text-bg-warning';
  }

  return 'text-bg-secondary';
});

// Guest graph mode: no alert API calls at all (mirrors AppLayout.vue's guard).
async function refreshAlertBadge() {
  if (!authStore.isAuthenticated) {
    return;
  }

  await alertsStore.fetchUnresolved();
}

onMounted(refreshAlertBadge);
watch(() => authStore.isAuthenticated, refreshAlertBadge);

async function handleLogout() {
  await authStore.logout();
  router.push({ name: 'login' });
}
</script>
