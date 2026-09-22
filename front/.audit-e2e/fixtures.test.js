import { describe, expect, it } from 'vitest';

import { mockApi } from './fixtures.mjs';

async function requestFixture(pathname, { method = 'GET', role = 'admin' } = {}) {
  let matcher;
  let handler;
  const page = {
    route: async (registeredMatcher, registeredHandler) => {
      matcher = registeredMatcher;
      handler = registeredHandler;
    },
  };
  const fulfilled = [];
  const route = {
    request: () => ({
      method: () => method,
      url: () => `http://fixture.test/api${pathname}`,
    }),
    fulfill: async (response) => fulfilled.push(response),
  };

  await mockApi(page, { role });

  expect(matcher(new URL('http://fixture.test/api' + pathname))).toBe(true);
  await handler(route);

  expect(fulfilled).toHaveLength(1);
  return {
    status: fulfilled[0].status,
    body: JSON.parse(fulfilled[0].body),
  };
}

describe('Gate 9 mock API fixture contract', () => {
  it('serves each configuration and account shell request with the view-required shape', async () => {
    const [runtime, general, alerts, email, systemInfo, users, roles, profile] = await Promise.all([
      requestFixture('/config/runtime'),
      requestFixture('/config/general'),
      requestFixture('/config/alerts'),
      requestFixture('/config/email'),
      requestFixture('/config/system-info'),
      requestFixture('/users'),
      requestFixture('/roles'),
      requestFixture('/profile'),
    ]);

    expect(runtime).toMatchObject({ status: 200, body: { alert_sound_enabled: expect.any(Boolean), app_url: expect.any(String) } });
    expect(general).toMatchObject({ status: 200, body: { data: { app_name: expect.any(String), app_url: expect.any(String) } } });
    expect(alerts).toMatchObject({ status: 200, body: { mail_enabled: expect.any(Boolean), alert_threshold: expect.any(Number) } });
    expect(email).toMatchObject({ status: 200, body: { mail_host: expect.any(String), password_configured: expect.any(Boolean) } });
    expect(systemInfo).toMatchObject({ status: 200, body: { data: { php_version: expect.any(String), laravel_version: expect.any(String), environment: expect.any(String), db_driver: expect.any(String) } } });
    expect(users.status).toBe(200);
    expect(users.body.data).toEqual(expect.arrayContaining([
      expect.objectContaining({ id: expect.any(Number), role: expect.objectContaining({ code: expect.any(String) }) })
    ]));
    expect(roles.status).toBe(200);
    expect(roles.body.data).toEqual(expect.arrayContaining([
      expect.objectContaining({ code: expect.any(String), name: expect.any(String) })
    ]));
    expect(profile).toMatchObject({ status: 200, body: { id: expect.any(Number), name: expect.any(String), role: expect.objectContaining({ code: expect.any(String) }) } });
  });

  it('keeps the representative sensor-detail response aligned with its catalog ID', async () => {
    const response = await requestFixture('/sensors/101');

    expect(response).toMatchObject({ status: 200, body: { data: { id: 101, device_id: 1 } } });
  });

  it('serves the device detail sensor-list and auth-transition logout contracts', async () => {
    const [sensorList, logout] = await Promise.all([
      requestFixture('/devices/1/sensor-list'),
      requestFixture('/auth/logout', { method: 'POST' }),
    ]);

    expect(sensorList.status).toBe(200);
    expect(sensorList.body.data).toEqual(expect.arrayContaining([
      expect.objectContaining({ id: 101, device_id: 1 })
    ]));
    expect(logout).toMatchObject({ status: 200, body: { message: expect.any(String) } });
  });

  it('fails closed for an undeclared API request', async () => {
    const response = await requestFixture('/not-a-fixture');

    expect(response.status).toBe(501);
    expect(response.body.message).toContain('Unmocked API fixture: GET /not-a-fixture');
  });
});
