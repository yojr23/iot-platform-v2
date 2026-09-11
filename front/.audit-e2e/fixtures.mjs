// Sandbox-only Playwright fixtures for the Gate 0 mobile audit (audit.md).
// Not committed — see front/.gitignore. Mocks only what front/src/api/* actually calls.

const AUTH_TOKEN_KEY = 'iot-platform-v2.auth_token';

const USERS = {
  user: { id: 2, name: 'Astra User', email: 'user@astra.test', is_admin: false },
  admin: { id: 1, name: 'Astra Admin', email: 'admin@astra.test', is_admin: true }
};

const LONG = 'Laboratorio de Instrumentación y Control Ambiental de Procesos Industriales Distribuidos';

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

function alert(i, mode) {
  return {
    id: i,
    severity: i % 3 === 0 ? 'danger' : i % 2 === 0 ? 'warning' : 'info',
    message: mode === 'long'
      ? `Alerta critica prolongada: ${LONG} reporto un valor fuera de rango sostenido durante mas de treinta minutos consecutivos en el sensor asociado.`
      : `Alert message ${i}`,
    resolved_at: null,
    created_at: new Date(Date.now() - i * 60000).toISOString(),
    sensor: { id: i, name: `Sensor ${i}` }
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
      if (token) window.localStorage.setItem(key, token);
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
      return json(route, { data: { token: 'astra-user-token', user: USERS.user } });
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
    if (p === '/dashboard/preferences') {
      return json(route, { data: { monitors: sensors.slice(0, 3).map((s) => s.id), poll_interval: 2000 } });
    }
    if (p === '/devices') return json(route, { data: devices });
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

    return json(route, { data: [] });
  });
}
