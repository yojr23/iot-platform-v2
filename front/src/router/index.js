import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

const AppLayout = () => import('@/layouts/AppLayout.vue');
const DashboardView = () => import('@/views/DashboardView.vue');
const NotFoundView = () => import('@/views/NotFoundView.vue');

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { publicOnly: true }
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { publicOnly: true }
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { publicOnly: true }
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/auth/ResetPasswordView.vue'),
      meta: { publicOnly: true }
    },
    {
      path: '/verify-email/:id/:hash',
      name: 'verify-email',
      component: () => import('@/views/auth/EmailVerificationView.vue')
    },
    {
      path: '/',
      component: AppLayout,
      children: [
        {
          path: '',
          redirect: { name: 'dashboard' }
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView
        },
        {
          path: 'sensors',
          name: 'sensors',
          component: () => import('@/views/SensorsView.vue'),
          meta: { requiresAuth: true }
        },
        {
          path: 'sensors/:id',
          name: 'sensor-detail',
          component: () => import('@/views/SensorDetailView.vue'),
          props: true,
          meta: { requiresAuth: true }
        },
        {
          path: 'devices',
          name: 'devices',
          component: () => import('@/views/DevicesView.vue'),
          meta: { requiresAuth: true }
        },
        {
          path: 'devices/:id',
          name: 'device-detail',
          component: () => import('@/views/DeviceDetailView.vue'),
          props: true,
          meta: { requiresAuth: true }
        },
        {
          path: 'alerts',
          name: 'alerts',
          component: () => import('@/views/AlertsView.vue'),
          meta: { requiresAuth: true }
        },
        {
          path: 'alerts/:id',
          name: 'alert-detail',
          component: () => import('@/views/AlertDetailView.vue'),
          props: true,
          meta: { requiresAuth: true }
        },
        {
          path: 'alert-rules',
          name: 'alert-rules',
          component: () => import('@/views/AlertRulesView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'config',
          name: 'config',
          component: () => import('@/views/ConfigView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'config/general',
          name: 'config-general',
          component: () => import('@/views/config/GeneralConfigView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'config/alerts',
          name: 'config-alerts',
          component: () => import('@/views/config/AlertConfigView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'config/email',
          name: 'config-email',
          component: () => import('@/views/config/EmailConfigView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'config/diagnostics',
          name: 'config-diagnostics',
          component: () => import('@/views/config/DiagnosticsConfigView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'labs',
          name: 'labs',
          component: () => import('@/views/CatalogAdminView.vue'),
          props: { type: 'labs' },
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'sensor-types',
          name: 'sensor-types',
          component: () => import('@/views/CatalogAdminView.vue'),
          props: { type: 'sensor-types' },
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'device-types',
          name: 'device-types',
          component: () => import('@/views/CatalogAdminView.vue'),
          props: { type: 'device-types' },
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/UserRolesView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'metrics',
          name: 'metrics',
          component: () => import('@/views/MetricsView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/views/ProfileView.vue'),
          meta: { requiresAuth: true }
        }
      ]
    },
    {
      path: '/404',
      name: 'not-found',
      component: NotFoundView
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'not-found' }
    }
  ]
});

router.beforeEach(async (to) => {
  const authStore = useAuthStore();

  if (!authStore.initialized) {
    await authStore.initializeAuth();
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      name: 'login',
      query: { redirect: to.fullPath }
    };
  }

  if (to.meta.requiresAdmin && !authStore.user?.is_admin) {
    return {
      name: 'dashboard',
      query: { denied: 'admin' }
    };
  }

  if (to.meta.publicOnly && authStore.isAuthenticated) {
    return { name: 'dashboard' };
  }

  return true;
});

export default router;
