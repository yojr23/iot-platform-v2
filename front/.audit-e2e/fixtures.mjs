// Sandbox-only Playwright fixtures for the Gate 0 mobile audit (audit.md).
// Not committed — see front/.gitignore. Mocks only what front/src/api/* actually calls.

const AUTH_TOKEN_KEY = 'iot-platform-v2.auth_token';

const USERS = {
  user: {
    id: 2,
    name: 'Astra User',
    email: 'user@astra.test',
    role: { code: 'user', name: 'User', level: 1 },
    permissions: [
      'dashboard.view', 'device.view', 'sensor.view', 'sensor_reading.view',
      'alert.view', 'alert.resolve', 'alert_rule.view'
    ]
  },
  admin: {
    id: 1,
    name: 'Astra Admin',
    email: 'admin@astra.test',
    role: { code: 'admin', name: 'Administrator', level: 2 },
    permissions: [
      'dashboard.view', 'device.view', 'device.create', 'device.update', 'device.delete', 'device.api_key.rotate',
      'sensor.view', 'sensor.create', 'sensor.update', 'sensor.delete',
      'sensor_reading.view', 'sensor_reading.export',
      'alert.view', 'alert.resolve',
      'alert_rule.view', 'alert_rule.create', 'alert_rule.update', 'alert_rule.delete',
      'user.view', 'user.role.assign', 'role.view',
      'system_setting.view', 'system_setting.update',
      'public_monitoring.manage', 'audit.view'
    ]
  }
};

const LONG = 'Laboratorio de Instrumentación y Control Ambiental de Procesos Industriales Distribuidos';

// These contracts are intentionally explicit: the configuration and account shells issue
// independent requests on mount, so a generic successful fallback would hide missing fixtures.
const CONFIG_RUNTIME = {
  alert_sound_enabled: true,
  app_url: 'https://iot-platform.fixture.test'
};

const CONFIG_GENERAL = {
  data: {
    app_name: 'SINOA Fixture',
    app_url: CONFIG_RUNTIME.app_url
  }
};

const CONFIG_ALERTS = {
  mail_enabled: true,
  alert_sound_enabled: true,
  alert_threshold: 5,
  danger_email_rate_limit_seconds: 60
};

const CONFIG_EMAIL = {
  mail_mailer: 'smtp',
  mail_host: 'smtp.fixture.test',
  mail_port: 587,
  mail_username: 'alerts@fixture.test',
  mail_encryption: 'tls',
  mail_from_address: 'alerts@fixture.test',
  mail_from_name: 'SINOA Fixture',
  mail_to: 'operator@fixture.test',
  password_configured: true
};

const CONFIG_SYSTEM_INFO = {
  data: {
    php_version: '8.3.0',
    laravel_version: '12.0.0',
    environment: 'testing',
    db_driver: 'mysql'
  }
};

const ROLES = [
  { id: 1, code: 'user', name: 'User', level: 1, permissions: USERS.user.permissions },
  { id: 2, code: 'admin', name: 'Administrator', level: 2, permissions: USERS.admin.permissions }
];

// Mirrors back/app/Http/Controllers/Api/DashboardController.php::publicDevices()
// device.sensors[] is what SensorMonitorBoard.vue reads (availableSensors/firstSelectableSensor).
function deviceSensor(deviceIndex, sensorIndex, mode) {
  const id = deviceIndex * 100 + sensorIndex;
  return {
    id,
    name: mode === 'long' ? `${LONG} - Sensor ${id}` : `Sensor ${id}`,
    status: sensorIndex % 2 === 0,
    unit: 'C',
    sensor_type: { id: 1, name: 'Temperature', unit: 'C' },
    device_id: deviceIndex
  };
}

function sensorsPerDeviceFor(mode) {
  if (mode === 'empty') return 0;
  if (mode === 'dense') return 4;
  return 2;
}

function device(i, mode, sensorsPerDevice = sensorsPerDeviceFor(mode)) {
  const sensors = Array.from({ length: sensorsPerDevice }, (_, s) => deviceSensor(i, s + 1, mode));

  return {
    id: i,
    name: mode === 'long' ? `${LONG} - Device ${i}` : `Device ${i}`,
    status: i % 2 === 0,
    is_active: true,
    last_communication: new Date().toISOString(),
    device_type: { id: 1, name: 'Gateway' },
    lab: { id: 1, name: mode === 'long' ? LONG : 'Lab A', area: 'A', process_line: 'L1' },
    sensors
  };
}

export function alert(i, mode) {
  const severity = i % 3 === 0 ? 'danger' : i % 2 === 0 ? 'warning' : 'info';
  const message = mode === 'long'
    ? `Alerta critica prolongada: ${LONG} reporto un valor fuera de rango sostenido durante mas de treinta minutos consecutivos en el sensor asociado.`
    : `Alert message ${i}`;

  return {
    id: i,
    resolved: false,
    resolved_at: null,
    created_at: new Date(Date.now() - i * 60000).toISOString(),
    updated_at: new Date().toISOString(),
    sensor_reading: {
      id: i,
      value: 20 + i,
      reading_time: new Date(Date.now() - i * 60000).toISOString()
    },
    alert_rule: {
      id: i,
      name: `Rule ${i}`,
      severity,
      message,
      min_value: null,
      max_value: 30
    },
    sensor: {
      id: i,
      name: `Sensor ${i}`,
      sensor_type: { id: i, name: 'Temperature', unit: '°C' }
    },
    device: {
      id: i,
      name: `Device ${i}`,
      lab: { id: 1, name: 'Lab A' }
    }
  };
}

function readings(n) {
  const now = Date.now();
  return Array.from({ length: n }, (_, i) => ({
    id: i + 1,
    value: 20 + Math.sin(i / 3) * 5,
    reading_time: new Date(now - (n - i) * 5000).toISOString()
  }));
}

function countFor(mode, normalCount) {
  if (mode === 'empty') return 0;
  if (mode === 'dense') return Math.max(normalCount * 8, 24);
  return normalCount;
}

function json(route, body, status = 200) {
  return route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body)
  });
}

export async function installAuth(page, role) {
  await page.addInitScript(
    ({ key, token }) => {
      // Seed the requested role once per Playwright context. An init script runs on every
      // document navigation, so unconditional seeding would silently restore the original
      // admin token after the auth-transition interaction logs out.
      const seededKey = `${key}.audit-seeded`;
      if (token && !window.sessionStorage.getItem(seededKey)) {
        window.localStorage.setItem(key, token);
        window.sessionStorage.setItem(seededKey, 'true');
      }
    },
    { key: AUTH_TOKEN_KEY, token: role === 'guest' ? null : `astra-${role}-token` }
  );
}

export async function mockApi(page, { role = 'guest', mode = 'normal' } = {}) {
  const deviceCount = countFor(mode, 5);
  const alertCount = countFor(mode, 4);

  const devices = Array.from({ length: deviceCount }, (_, i) => device(i + 1, mode));
  // Flat /sensors list mirrors the sensors embedded per-device above (device_id now correct,
  // instead of the old generator that hardcoded every sensor to device_id: 1).
  const sensors = devices.flatMap((d) => d.sensors);
  const alerts = Array.from({ length: alertCount }, (_, i) => alert(i + 1, mode));

  await page.route((url) => url.pathname.startsWith('/api/'), async (route) => {
    const req = route.request();
    const url = new URL(req.url());
    const p = url.pathname.replace(/^.*\/api/, '');
    const method = req.method();

    if (p === '/auth/me') {
      if (role === 'guest') return json(route, { message: 'Unauthenticated.' }, 401);
      return json(route, { data: USERS[role] });
    }
    if (p === '/auth/login' && method === 'POST') {
      // AuthApiController::tokenResponse() returns this payload at the response root. The
      // auth store reads response.data.access_token before it fetches /auth/me.
      return json(route, { access_token: 'astra-user-token', user: USERS.user });
    }
    if (p === '/auth/logout' && method === 'POST') {
      return json(route, { message: 'Sesión API cerrada correctamente.' });
    }
    // Shape mirrors back/app/Http/Controllers/Api/DashboardController.php::dashboardPayload()
    // (flat total_devices/active_devices/total_sensors/active_alerts/unresolved_alerts, not
    // {devices,sensors,alerts} counts) — MetricsCards.vue/DashboardView.vue read these exact keys.
    if (p === '/dashboard/public') {
      return json(route, {
        total_devices: devices.length,
        active_devices: devices.filter((d) => d.status && d.is_active).length,
        total_sensors: sensors.length,
        active_alerts: alertCount,
        unresolved_alerts: alerts.filter((a) => !a.resolved_at).length,
        latest_readings: [],
        system_status: 'ok',
        devices
      });
    }
    if (p === '/dashboard/metrics') {
      return json(route, {
        total_devices: devices.length,
        active_devices: devices.filter((d) => d.status && d.is_active).length,
        total_sensors: sensors.length,
        active_alerts: alertCount,
        unresolved_alerts: alerts.filter((a) => !a.resolved_at).length,
        latest_readings: [],
        system_status: 'ok'
      });
    }
    // DashboardView consumes the graph-only bootstrap/catalog contracts, not the broader
    // `/dashboard/public` payload. Keep the fixture aligned so lifecycle rows exercise the
    // rendered workspace instead of an empty catalog.
    if (p === '/public/graph/bootstrap') {
      return json(route, { version: 1, default_sensor_id: sensors[0]?.id ?? null, devices });
    }
    if (p === '/dashboard/graph-catalog') {
      if (role === 'guest') return json(route, { message: 'Unauthenticated.' }, 401);
      return json(route, { version: 1, default_sensor_id: sensors[0]?.id ?? null, devices });
    }
    if (/^\/public\/graph\/sensors\/\d+\/series$/.test(p) || /^\/sensors\/\d+\/series$/.test(p)) {
      const points = readings(mode === 'dense' ? 60 : 20).map((reading) => ({
        timestamp: reading.reading_time,
        value: reading.value,
        reading_id: reading.id
      }));
      return json(route, { points, stats: { min: 15, max: 25, mean: 20, count: points.length }, truncated: false });
    }
    if (p === '/dashboard/preferences') {
      return json(route, { data: { monitors: sensors.slice(0, 3).map((s) => s.id), poll_interval: 2000 } });
    }
    if (p === '/config/runtime') return json(route, CONFIG_RUNTIME);
    if (p === '/config/general') return json(route, CONFIG_GENERAL);
    if (p === '/config/alerts') return json(route, CONFIG_ALERTS);
    if (p === '/config/email') return json(route, CONFIG_EMAIL);
    if (p === '/config/system-info') return json(route, CONFIG_SYSTEM_INFO);
    if (p === '/roles') return json(route, { data: ROLES });
    if (p === '/profile') {
      const user = USERS[role] || USERS.user;
      return json(route, {
        ...user,
        department: 'Laboratorio de pruebas',
        created_at: '2026-01-01T00:00:00.000Z',
        updated_at: '2026-01-01T00:00:00.000Z'
      });
    }
    if (p === '/devices') return json(route, { data: devices });
    if (/^\/devices\/\d+\/sensor-list$/.test(p)) {
      const deviceId = Number(p.split('/')[2]);
      const match = devices.find((d) => d.id === deviceId);
      return json(route, { data: match ? match.sensors : [] });
    }
    if (/^\/devices\/\d+\/sensors$/.test(p)) {
      const deviceId = Number(p.split('/')[2]);
      const match = devices.find((d) => d.id === deviceId);
      return json(route, { data: match ? match.sensors : sensors.slice(0, 3) });
    }
    if (/^\/devices\/\d+$/.test(p)) return json(route, { data: devices[0] || device(1, mode) });
    if (p === '/sensors') return json(route, { data: sensors });
    // Real shape: SensorApiController::latestReadings() returns response()->json($readings) —
    // a bare array, not {data:[...]}. SensorMonitorBoard.vue reads `response.data` directly and
    // expects an array; the old {data:[...]} wrapper made every monitor look empty (0 puntos).
    if (/^\/sensors\/\d+\/latest-readings$/.test(p)) {
      const limit = Number(url.searchParams.get('limit')) || 10;
      return json(route, readings(Math.max(1, Math.min(limit, 100))));
    }
    if (/^\/sensors\/\d+\/readings$/.test(p)) return json(route, { data: readings(mode === 'dense' ? 60 : 20) });
    if (/^\/sensors\/\d+$/.test(p)) {
      const sensorId = Number(p.split('/')[2]);
      return json(route, { data: sensors.find((s) => s.id === sensorId) || sensors[0] || null });
    }
    // Real shape: AlertController::active() returns {count, alerts} at the top level,
    // not wrapped in {data: [...]} — alerts.js store's fetchActiveAlerts() reads
    // response.data.alerts / response.data.count directly (see AlertController.php:52-58).
    if (p === '/alerts/active') {
      return json(route, { count: alerts.filter((a) => !a.resolved_at).length, alerts });
    }
    if (p === '/alerts' || p === '/alerts/unresolved') {
      return json(route, { data: alerts, meta: { total: alerts.length }, links: {} });
    }
    if (/^\/alerts\/\d+$/.test(p)) return json(route, { data: alerts[0] || alert(1, mode) });
    if (p === '/alert-rules') return json(route, { data: [] });
    if (p === '/users') return json(route, { data: [USERS.user, USERS.admin] });
    if (p === '/metrics') {
      return json(route, {
        total_devices: devices.length,
        active_devices: devices.filter((d) => d.status && d.is_active).length,
        total_sensors: sensors.length,
        readings_today: 1284,
        active_alerts: alertCount,
        total_alert_rules: 6,
        enabled_rules: 4,
        total_labs: 2,
        uptime_percent: 99.4
      });
    }
    if (p.startsWith('/labs') || p.startsWith('/sensor-types') || p.startsWith('/device-types')) {
      return json(route, { data: [] });
    }

    // A new matrix route must declare its API fixture explicitly. Returning a generic
    // successful empty response masks router/harness drift as a healthy mock run.
    return json(route, { message: `Unmocked API fixture: ${method} ${p}` }, 501);
  });
}
