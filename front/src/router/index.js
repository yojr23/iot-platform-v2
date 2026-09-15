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
      path: '/verification-required',
      name: 'verification-required',
      component: () => import('@/views/auth/VerificationRequiredView.vue'),
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
          component: DashboardView,
        },
        {
          path: 'sensors',
          name: 'sensors',
          component: () => import('@/views/SensorsView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'sensor.view' }
        },
        {
          path: 'sensors/:id',
          name: 'sensor-detail',
          component: () => import('@/views/SensorDetailView.vue'),
          props: true,
          meta: { requiresAuth: true, requiresPermission: 'sensor.view' }
        },
        {
          path: 'devices',
          name: 'devices',
          component: () => import('@/views/DevicesView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'device.view' }
        },
        {
          path: 'devices/:id',
          name: 'device-detail',
          component: () => import('@/views/DeviceDetailView.vue'),
          props: true,
          meta: { requiresAuth: true, requiresPermission: 'device.view' }
        },
        {
          path: 'alerts',
          name: 'alerts',
          component: () => import('@/views/AlertsView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'alert.view' }
        },
        {
          path: 'alerts/:id',
          name: 'alert-detail',
          component: () => import('@/views/AlertDetailView.vue'),
          props: true,
          meta: { requiresAuth: true, requiresPermission: 'alert.view' }
        },
        {
          path: 'alert-rules',
          name: 'alert-rules',
          component: () => import('@/views/AlertRulesView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'alert_rule.view' }
        },
        {
          path: 'config',
          name: 'config',
          component: () => import('@/views/ConfigView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'system_setting.view' }
        },
        {
          path: 'config/general',
          name: 'config-general',
          component: () => import('@/views/config/GeneralConfigView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'system_setting.view' }
        },
        {
          path: 'config/alerts',
          name: 'config-alerts',
          component: () => import('@/views/config/AlertConfigView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'system_setting.view' }
        },
        {
          path: 'config/email',
          name: 'config-email',
          component: () => import('@/views/config/EmailConfigView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'system_setting.view' }
        },
        {
          path: 'config/diagnostics',
          name: 'config-diagnostics',
          component: () => import('@/views/config/DiagnosticsConfigView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'system_setting.view' }
        },
        {
          path: 'labs',
          name: 'labs',
          component: () => import('@/views/CatalogAdminView.vue'),
          props: { type: 'labs' },
          meta: { requiresAuth: true, requiresPermission: 'device.view' }
        },
        {
          path: 'sensor-types',
          name: 'sensor-types',
          component: () => import('@/views/CatalogAdminView.vue'),
          props: { type: 'sensor-types' },
          meta: { requiresAuth: true, requiresPermission: 'device.view' }
        },
        {
          path: 'device-types',
          name: 'device-types',
          component: () => import('@/views/CatalogAdminView.vue'),
          props: { type: 'device-types' },
          meta: { requiresAuth: true, requiresPermission: 'device.view' }
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/UserRolesView.vue'),
          meta: { requiresAuth: true, requiresPermission: 'user.view' }
        },
        {
          path: 'metrics',
          name: 'metrics',
          component: () => import('@/views/MetricsView.vue'),
          meta: { requiresAuth: true }
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

  if (to.meta.requiresPermission && !authStore.can(to.meta.requiresPermission)) {
    return {
      name: 'dashboard',
      query: { denied: 'permission' }
    };
  }

  if (to.meta.publicOnly && authStore.isAuthenticated) {
    return { name: 'dashboard' };
  }

  return true;
});

export default router;
