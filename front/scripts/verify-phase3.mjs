import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('..', import.meta.url));

const requiredFiles = [
  'index.html',
  'vite.config.js',
  '.env.example',
  'src/main.js',
  'src/App.vue',
  'src/api/client.js',
  'src/api/auth.js',
  'src/api/dashboard.js',
  'src/api/alerts.js',
  'src/api/alertRules.js',
  'src/api/sensors.js',
  'src/api/devices.js',
  'src/api/config.js',
  'src/assets/styles/main.scss',
  'src/assets/sounds/.gitkeep',
  'src/components/base/BaseButton.vue',
  'src/components/base/BaseAlert.vue',
  'src/components/base/BaseInput.vue',
  'src/components/base/LoadingSpinner.vue',
  'src/components/layout/AppLayout.vue',
  'src/components/layout/NavBar.vue',
  'src/layouts/AuthLayout.vue',
  'src/layouts/AppLayout.vue',
  'src/router/index.js',
  'src/stores/auth.js',
  'src/stores/alerts.js',
  'src/views/auth/LoginView.vue',
  'src/views/auth/RegisterView.vue',
  'src/views/auth/ForgotPasswordView.vue',
  'src/views/auth/ResetPasswordView.vue',
  'src/views/auth/EmailVerificationView.vue',
  'src/views/DashboardView.vue',
  'src/views/SensorsView.vue',
  'src/views/SensorDetailView.vue',
  'src/views/AlertsView.vue',
  'src/views/AlertRulesView.vue',
  'src/views/ConfigView.vue',
  'src/views/NotFoundView.vue',
  'src/realtime/echo.js',
  'src/realtime/useAlertsRealtime.js'
];

const missing = requiredFiles.filter((file) => !existsSync(join(root, file)));

if (missing.length > 0) {
  console.error('Missing Phase 3 frontend files:');
  for (const file of missing) {
    console.error(`- ${file}`);
  }
  process.exit(1);
}

const packageJson = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8'));
const dependencies = {
  ...packageJson.dependencies,
  ...packageJson.devDependencies
};

const requiredDependencies = [
  'vue',
  '@vitejs/plugin-vue',
  'vite',
  'bootstrap',
  'axios',
  'vue-router',
  'pinia',
  'chart.js',
  'vue-chartjs',
  'laravel-echo',
  'pusher-js',
  'sass'
];

const missingDependencies = requiredDependencies.filter((name) => !dependencies[name]);

if (missingDependencies.length > 0) {
  console.error('Missing Phase 3 frontend dependencies:');
  for (const dependency of missingDependencies) {
    console.error(`- ${dependency}`);
  }
  process.exit(1);
}

const envExample = readFileSync(join(root, '.env.example'), 'utf8');
for (const variable of ['VITE_API_BASE_URL', 'VITE_PUSHER_APP_KEY', 'VITE_PUSHER_APP_CLUSTER']) {
  if (!envExample.includes(`${variable}=`)) {
    console.error(`Missing ${variable} in .env.example`);
    process.exit(1);
  }
}

const router = readFileSync(join(root, 'src/router/index.js'), 'utf8');
if (router.includes("name: 'verify-email',\n      component: EmailVerificationView,\n      meta: { publicOnly: true }")) {
  console.error('Email verification route must remain accessible even if a token exists.');
  process.exit(1);
}

const emailVerification = readFileSync(join(root, 'src/views/auth/EmailVerificationView.vue'), 'utf8');
if (!emailVerification.includes('resendVerification')) {
  console.error('Email verification view must expose resend verification support.');
  process.exit(1);
}

console.log('Phase 3 frontend structure looks complete.');
