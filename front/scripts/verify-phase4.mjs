import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));

const requiredFiles = [
  'src/components/dashboard/MetricsCards.vue',
  'src/components/dashboard/ActiveAlertsCard.vue',
  'src/components/dashboard/DeviceStatusList.vue',
  'src/components/dashboard/SensorChart.vue',
  'src/components/dashboard/RecentReadingsTable.vue',
  'src/components/alerts/AlertFilters.vue',
  'src/components/alerts/AlertItem.vue',
  'src/components/alerts/AlertList.vue',
  'src/components/sensors/SensorFilters.vue',
  'src/components/sensors/SensorList.vue',
  'src/components/sensors/SensorReadingsChart.vue',
  'src/components/sensors/SensorReadingsTable.vue',
  'src/components/devices/DeviceList.vue',
  'src/components/devices/DeviceStatusBadge.vue',
  'src/components/alert-rules/AlertRuleForm.vue',
  'src/components/alert-rules/AlertRuleList.vue',
  'src/components/alert-rules/AlertRuleModal.vue',
  'src/views/DevicesView.vue'
];

const missing = requiredFiles.filter((file) => !existsSync(join(root, file)));

if (missing.length > 0) {
  console.error('Missing Phase 4 frontend files:');
  for (const file of missing) {
    console.error(`- ${file}`);
  }
  process.exit(1);
}

const router = readFileSync(join(root, 'src/router/index.js'), 'utf8');
if (!router.includes("path: 'devices'") || !router.includes("name: 'devices'")) {
  console.error('Router must include protected /devices route.');
  process.exit(1);
}
if (!router.includes('requiresAdmin')) {
  console.error('Router must protect admin-only views with requiresAdmin metadata.');
  process.exit(1);
}
if (!router.includes('authStore.user?.is_admin')) {
  console.error('Router must check admin role before entering admin-only views.');
  process.exit(1);
}

const navbar = readFileSync(join(root, 'src/components/layout/NavBar.vue'), 'utf8');
if (!navbar.includes('/devices')) {
  console.error('Navbar must link to /devices.');
  process.exit(1);
}
if (!navbar.includes('laboratoryItems') || !navbar.includes('adminItems')) {
  console.error('Navbar must separate laboratory and admin navigation items.');
  process.exit(1);
}

const srcFilesToCheck = [
  'src/api/client.js',
  'src/views/DashboardView.vue',
  'src/views/AlertsView.vue',
  'src/views/SensorsView.vue',
  'src/views/SensorDetailView.vue',
  'src/views/DevicesView.vue',
  'src/views/AlertRulesView.vue',
  'src/views/ConfigView.vue'
];

for (const file of srcFilesToCheck) {
  const content = readFileSync(join(root, file), 'utf8');
  if (content.includes('localhost')) {
    console.error(`Hardcoded localhost found in ${file}`);
    process.exit(1);
  }
}

console.log('Phase 4 frontend migration structure looks complete.');
