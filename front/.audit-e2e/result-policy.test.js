import { describe, expect, it } from 'vitest';
import { isCleanResult } from './result-policy.mjs';

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
