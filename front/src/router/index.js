import { createRouter, createWebHistory } from 'vue-router';

import AppLayout from '@/layouts/AppLayout.vue';
import AlertRulesView from '@/views/AlertRulesView.vue';
import AlertDetailView from '@/views/AlertDetailView.vue';
import AlertsView from '@/views/AlertsView.vue';
import CatalogAdminView from '@/views/CatalogAdminView.vue';
import ConfigView from '@/views/ConfigView.vue';
import DashboardView from '@/views/DashboardView.vue';
import DeviceDetailView from '@/views/DeviceDetailView.vue';
import DevicesView from '@/views/DevicesView.vue';
import MetricsView from '@/views/MetricsView.vue';
import SensorsView from '@/views/SensorsView.vue';
import SensorDetailView from '@/views/SensorDetailView.vue';
import ProfileView from '@/views/ProfileView.vue';
import UserRolesView from '@/views/UserRolesView.vue';
import EmailVerificationView from '@/views/auth/EmailVerificationView.vue';
import ForgotPasswordView from '@/views/auth/ForgotPasswordView.vue';
import LoginView from '@/views/auth/LoginView.vue';
import RegisterView from '@/views/auth/RegisterView.vue';
import ResetPasswordView from '@/views/auth/ResetPasswordView.vue';
import NotFoundView from '@/views/NotFoundView.vue';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { publicOnly: true }
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: { publicOnly: true }
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPasswordView,
      meta: { publicOnly: true }
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: ResetPasswordView,
      meta: { publicOnly: true }
    },
    {
      path: '/verify-email/:id/:hash',
      name: 'verify-email',
      component: EmailVerificationView
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
          component: SensorsView,
          meta: { requiresAuth: true }
        },
        {
          path: 'sensors/:id',
          name: 'sensor-detail',
          component: SensorDetailView,
          props: true,
          meta: { requiresAuth: true }
        },
        {
          path: 'devices',
          name: 'devices',
          component: DevicesView,
          meta: { requiresAuth: true }
        },
        {
          path: 'devices/:id',
          name: 'device-detail',
          component: DeviceDetailView,
          props: true,
          meta: { requiresAuth: true }
        },
        {
          path: 'alerts',
          name: 'alerts',
          component: AlertsView,
          meta: { requiresAuth: true }
        },
        {
          path: 'alerts/:id',
          name: 'alert-detail',
          component: AlertDetailView,
          props: true,
          meta: { requiresAuth: true }
        },
        {
          path: 'alert-rules',
          name: 'alert-rules',
          component: AlertRulesView,
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'config',
          name: 'config',
          component: ConfigView,
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'labs',
          name: 'labs',
          component: CatalogAdminView,
          props: { type: 'labs' },
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'sensor-types',
          name: 'sensor-types',
          component: CatalogAdminView,
          props: { type: 'sensor-types' },
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'device-types',
          name: 'device-types',
          component: CatalogAdminView,
          props: { type: 'device-types' },
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'users',
          name: 'users',
          component: UserRolesView,
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'metrics',
          name: 'metrics',
          component: MetricsView,
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'profile',
          name: 'profile',
          component: ProfileView,
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
