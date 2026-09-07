// Sandbox-only Playwright fixtures for the Gate 0 mobile audit (audit.md).
// Not committed — see front/.gitignore. Mocks only what front/src/api/* actually calls.

const AUTH_TOKEN_KEY = 'iot-platform-v2.auth_token';

const USERS = {
  user: { id: 2, name: 'Astra User', email: 'user@astra.test', is_admin: false },
  admin: { id: 1, name: 'Astra Admin', email: 'admin@astra.test', is_admin: true }
};

const LONG = 'Laboratorio de Instrumentación y Control Ambiental de Procesos Industriales Distribuidos';

function device(i, mode) {
  return {
    id: i,
    name: mode === 'long' ? `${LONG} - Device ${i}` : `Device ${i}`,
    status: i % 2 === 0,
    is_active: true,
    device_type: { id: 1, name: 'Gateway' },
    lab: { id: 1, name: mode === 'long' ? LONG : 'Lab A' }
  };
}

function sensor(i, mode) {
  return {
    id: i,
    name: mode === 'long' ? `${LONG} - Sensor ${i}` : `Sensor ${i}`,
    sensor_type: { id: 1, name: 'Temperature', unit: 'C' },
    device_id: 1
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
  const sensorCount = countFor(mode, 6);
  const alertCount = countFor(mode, 4);

  const devices = Array.from({ length: deviceCount }, (_, i) => device(i + 1, mode));
  const sensors = Array.from({ length: sensorCount }, (_, i) => sensor(i + 1, mode));
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
    if (p === '/dashboard/public') {
      return json(route, { data: { devices: devices.length, sensors: sensors.length, alerts: alertCount } });
    }
    if (p === '/dashboard/metrics') {
      return json(route, { data: { devices: devices.length, sensors: sensors.length, active_alerts: alertCount } });
    }
    if (p === '/dashboard/preferences') {
      return json(route, { data: { monitors: sensors.slice(0, 3).map((s) => s.id), poll_interval: 2000 } });
    }
    if (p === '/devices') return json(route, { data: devices });
    if (/^\/devices\/\d+\/sensors$/.test(p)) return json(route, { data: sensors.slice(0, 3) });
    if (/^\/devices\/\d+$/.test(p)) return json(route, { data: devices[0] || device(1, mode) });
    if (p === '/sensors') return json(route, { data: sensors });
    if (/^\/sensors\/\d+\/latest-readings$/.test(p)) return json(route, { data: readings(1) });
    if (/^\/sensors\/\d+\/readings$/.test(p)) return json(route, { data: readings(mode === 'dense' ? 60 : 20) });
    if (/^\/sensors\/\d+$/.test(p)) return json(route, { data: sensors[0] || sensor(1, mode) });
    if (p === '/alerts' || p === '/alerts/unresolved' || p === '/alerts/active') {
      return json(route, { data: alerts });
    }
    if (/^\/alerts\/\d+$/.test(p)) return json(route, { data: alerts[0] || alert(1, mode) });
    if (p === '/alert-rules') return json(route, { data: [] });
    if (p === '/users') return json(route, { data: [USERS.user, USERS.admin] });
    if (p === '/metrics') return json(route, { data: {} });
    if (p.startsWith('/labs') || p.startsWith('/sensor-types') || p.startsWith('/device-types')) {
      return json(route, { data: [] });
    }

    return json(route, { data: [] });
  });
}
