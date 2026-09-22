import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

import { isPageCorrect } from '../../.audit-e2e/result-policy.mjs';
import router from './index';

const matrixPath = path.resolve(process.cwd(), '.audit-e2e/task10-matrix.txt');

// These are deliberately exclusions rather than a second list of product routes.
// Every named route not listed here must have a Gate 9 matrix entry.
const GATE9_EXCLUDED_ROUTE_REASONS = {
  login: 'guest authentication flow',
  register: 'guest authentication flow',
  'verification-required': 'guest verification flow',
  'forgot-password': 'guest password-recovery flow',
  'reset-password': 'guest password-recovery flow',
  'verify-email': 'signed email-verification flow',
  'not-found': 'fallback page',
};

function matrixRows() {
  return fs.readFileSync(matrixPath, 'utf8')
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'))
    .map((line) => {
      const [route, role, , , , , expectedMarker] = line.split('|');
      return { route, role, expectedMarker };
    });
}

function representativePath(route) {
  // The fixture catalog embeds the first device's sensor as ID 101. Keeping this
  // representative ID real proves the detail request and its rendered identity agree.
  if (route.name === 'sensor-detail') return '/sensors/101';
  return route.path.replace(/:([^/]+)/g, '1');
}

function certifiableProductRoutes() {
  return router.getRoutes().filter((route) => (
    typeof route.name === 'string' && !GATE9_EXCLUDED_ROUTE_REASONS[route.name]
  ));
}

describe('Gate 9 router coverage contract', () => {
  it('covers every named product route from the active router in the mock matrix', () => {
    const matrixRoutes = new Set(matrixRows().map(({ route }) => route));
    const missing = certifiableProductRoutes()
      .map(representativePath)
      .filter((path) => !matrixRoutes.has(path));

    expect(missing).toEqual([]);
  });

  it('represents the detail and focused configuration routes in the matrix', () => {
    const matrixRoutes = new Set(matrixRows().map(({ route }) => route));

    expect([...matrixRoutes]).toEqual(expect.arrayContaining([
      '/devices/1',
      '/sensors/101',
      '/alerts/1',
      '/config/general',
      '/config/alerts',
      '/config/email',
      '/config/diagnostics',
    ]));
  });

  it('retains an administrator role for permission-gated route coverage', () => {
    const rows = matrixRows();

    certifiableProductRoutes()
      .filter((route) => route.meta.requiresPermission)
      .forEach((route) => {
        const path = representativePath(route);
        expect(rows.some((row) => row.route === path && row.role === 'admin')).toBe(true);
      });
  });

  it('keeps guest, signed-flow, and fallback exclusions explicit', () => {
    expect(GATE9_EXCLUDED_ROUTE_REASONS).toEqual({
      login: 'guest authentication flow',
      register: 'guest authentication flow',
      'verification-required': 'guest verification flow',
      'forgot-password': 'guest password-recovery flow',
      'reset-password': 'guest password-recovery flow',
      'verify-email': 'signed email-verification flow',
      'not-found': 'fallback page',
    });
  });

  it('requires exact final route identity instead of accepting a detail-route prefix', () => {
    expect(isPageCorrect({
      route: '/devices/1',
      finalUrl: 'http://127.0.0.1:5173/devices/10',
      heading: 'Device 10',
    }, { expectedMarker: 'Device' })).toBe(false);
  });

  it('uses a catalog sensor ID and the actual fixture device heading for detail rows', () => {
    const rows = matrixRows();
    const sensorDetail = rows.find((row) => row.route === '/sensors/101');
    const deviceDetail = rows.find((row) => row.route === '/devices/1');

    expect(sensorDetail).toMatchObject({ route: '/sensors/101', expectedMarker: 'Sensor 101' });
    expect(deviceDetail).toMatchObject({ route: '/devices/1', expectedMarker: 'Device 1' });
  });
});
