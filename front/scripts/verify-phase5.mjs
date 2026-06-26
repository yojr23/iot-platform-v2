import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));

function read(relativePath) {
  return readFileSync(join(root, relativePath), 'utf8');
}

function assertIncludes(content, expected, message) {
  if (!content.includes(expected)) {
    console.error(message);
    process.exit(1);
  }
}

const requiredFiles = [
  'src/realtime/echo.js',
  'src/realtime/useAlertsRealtime.js',
  'src/realtime/useSensorRealtime.js',
  'src/stores/alerts.js',
  'src/components/alerts/AlertToast.vue',
  'src/utils/sound.js'
];

const missing = requiredFiles.filter((file) => !existsSync(join(root, file)));

if (missing.length > 0) {
  console.error('Missing Phase 5 realtime files:');
  for (const file of missing) {
    console.error(`- ${file}`);
  }
  process.exit(1);
}

const envExample = read('.env.example');
for (const variable of [
  'VITE_PUSHER_APP_KEY=',
  'VITE_PUSHER_APP_CLUSTER=',
  'VITE_PUSHER_FORCE_TLS=',
  'VITE_PUSHER_HOST=',
  'VITE_PUSHER_PORT=',
  'VITE_PUSHER_SCHEME='
]) {
  assertIncludes(envExample, variable, `front/.env.example must define ${variable}`);
}

const forbiddenSecretPatterns = [
  'PUSHER_APP_SECRET',
  'DB_PASSWORD',
  'MAIL_PASSWORD'
];

for (const file of [
  '.env.example',
  'src/realtime/echo.js',
  'src/realtime/useAlertsRealtime.js',
  'src/realtime/useSensorRealtime.js',
  'src/stores/alerts.js'
]) {
  const content = read(file);
  for (const pattern of forbiddenSecretPatterns) {
    if (content.includes(pattern)) {
      console.error(`Forbidden secret-like variable found in ${file}: ${pattern}`);
      process.exit(1);
    }
  }
}

const echo = read('src/realtime/echo.js');
for (const expected of [
  'VITE_PUSHER_FORCE_TLS',
  'VITE_PUSHER_HOST',
  'VITE_PUSHER_PORT',
  'VITE_PUSHER_SCHEME',
  'getEcho',
  'disconnectEcho'
]) {
  assertIncludes(echo, expected, `echo.js must include ${expected}`);
}

const alertsRealtime = read('src/realtime/useAlertsRealtime.js');
for (const expected of [
  "ALERTS_CHANNEL = 'alerts'",
  "ALERTS_EVENT = 'NewAlertTriggered'",
  'subscribeAlerts',
  'unsubscribeAlerts',
  'reconnect',
  'isRealtimeEnabled',
  'isConnected'
]) {
  assertIncludes(alertsRealtime, expected, `useAlertsRealtime.js must include ${expected}`);
}

const sensorRealtime = read('src/realtime/useSensorRealtime.js');
for (const expected of [
  "SENSOR_EVENT = 'NewSensorReading'",
  'sensor.${sensorId}',
  'subscribeSensor',
  'unsubscribeSensor'
]) {
  assertIncludes(sensorRealtime, expected, `useSensorRealtime.js must include ${expected}`);
}

const alertsStore = read('src/stores/alerts.js');
for (const expected of [
  'latestAlert',
  'realtimeStatus',
  'soundEnabled',
  'fetchActiveAlerts',
  'fetchAlerts',
  'resolveAlert',
  'resolveAll',
  'addRealtimeAlert',
  'setSoundEnabled',
  'setRealtimeStatus'
]) {
  assertIncludes(alertsStore, expected, `alerts store must include ${expected}`);
}
assertIncludes(alertsStore, 'notifyNew', 'alerts store fetchActiveAlerts must support notifying new snapshot alerts.');
assertIncludes(alertsStore, 'newAlerts', 'alerts store must return newly discovered alerts from polling snapshots.');

const appLayout = read('src/components/layout/AppLayout.vue');
assertIncludes(appLayout, 'AlertToast', 'App layout must render AlertToast.');
assertIncludes(appLayout, 'useAlertsRealtime', 'App layout must subscribe to alerts realtime.');
assertIncludes(appLayout, 'setInterval', 'App layout must poll active alerts globally as a realtime fallback.');
assertIncludes(appLayout, 'playAlertSound', 'App layout polling fallback must play alert sounds for new alerts.');
assertIncludes(appLayout, 'notifyNew: true', 'App layout polling fallback must request new-alert notifications.');

const alertToast = read('src/components/alerts/AlertToast.vue');
assertIncludes(alertToast, '<Transition', 'AlertToast must fade in/out with a Vue transition.');
assertIncludes(alertToast, 'alert-toast-fade', 'AlertToast must define fade transition classes.');
assertIncludes(alertToast, '9000', 'AlertToast must auto-dismiss after the configured timeout.');

const navBar = read('src/components/layout/NavBar.vue');
assertIncludes(navBar, 'realtimeStatus', 'NavBar must expose realtime status.');
assertIncludes(navBar, 'unresolvedCount', 'NavBar must show unresolved alert count.');

const activeAlertsCard = read('src/components/dashboard/ActiveAlertsCard.vue');
assertIncludes(activeAlertsCard, 'fetchActiveAlerts', 'ActiveAlertsCard must use alerts store polling/fetch fallback.');

const alertsView = read('src/views/AlertsView.vue');
assertIncludes(alertsView, 'latestAlert', 'AlertsView must react to latest realtime alert.');

const sensorDetail = read('src/views/SensorDetailView.vue');
assertIncludes(sensorDetail, 'useSensorRealtime', 'SensorDetailView must prepare sensor realtime subscription.');

console.log('Phase 5 realtime structure looks complete.');
