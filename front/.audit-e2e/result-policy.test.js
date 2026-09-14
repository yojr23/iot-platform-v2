import { describe, expect, it } from 'vitest';
import { isCleanResult, isPageCorrect } from './result-policy.mjs';

const healthyRun = {
  exitCode: 0,
  navError: null,
  hasOverflow: false,
  consoleErrors: 0,
  pageErrors: 0,
  failedRequests: 0,
  interaction: null
};

describe('responsive audit result policy', () => {
  it('rejects a captured interaction exception even when every transport and layout check is healthy', () => {
    expect(isCleanResult({
      ...healthyRun,
      interaction: { error: 'TimeoutError: locator.click timed out' }
    })).toBe(false);
  });

  it('rejects an interaction that explicitly reports a failed assertion', () => {
    expect(isCleanResult({
      ...healthyRun,
      interaction: { pass: false }
    })).toBe(false);
  });

  it('accepts a healthy required interaction', () => {
    expect(isCleanResult({
      ...healthyRun,
      interaction: { pass: true }
    })).toBe(true);
  });
});

describe('page identity assertions', () => {
  const baseResult = {
    ...healthyRun,
    route: '/dashboard',
    finalUrl: 'http://127.0.0.1:5173/dashboard',
    heading: 'Tu espacio de monitoreo'
  };

  it('accepts a result that lands on the correct route with the expected marker', () => {
    expect(isPageCorrect(baseResult, { expectedMarker: 'monitoreo' })).toBe(true);
  });

  it('rejects a result that redirects to login (denied=permission)', () => {
    expect(isPageCorrect({
      ...baseResult,
      finalUrl: 'http://127.0.0.1:5173/login?redirect=/dashboard'
    }, { expectedMarker: 'monitoreo' })).toBe(false);
  });

  it('rejects a result whose final pathname does not match the requested route', () => {
    expect(isPageCorrect({
      ...baseResult,
      route: '/devices',
      finalUrl: 'http://127.0.0.1:5173/dashboard'
    })).toBe(false);
  });

  it('rejects when expected heading marker is missing from the page', () => {
    expect(isPageCorrect(baseResult, { expectedMarker: 'Usuarios' })).toBe(false);
  });

  it('rejects when finalUrl is null (navigation failed)', () => {
    expect(isPageCorrect({
      ...baseResult,
      finalUrl: null
    })).toBe(false);
  });

  it('accepts a detail sub-route matching a route prefix', () => {
    expect(isPageCorrect({
      ...baseResult,
      route: '/devices',
      finalUrl: 'http://127.0.0.1:5173/devices/1'
    })).toBe(true);
  });
});
