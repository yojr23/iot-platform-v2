import { describe, expect, it } from 'vitest';
import { buildAuthTransitionEvidence, isAuthTransitionPass } from './auth-transition.mjs';

describe('mocked auth transition interaction contract', () => {
  it('returns pass only when the admin-to-standard evidence is complete', () => {
    const evidence = buildAuthTransitionEvidence({
      startedAsAdministrator: true,
      clearedAuthenticatedState: true,
      establishedStandardUser: true,
      monitorVisible: true,
      rangeControlsAvailable: true,
      populatedReadings: true,
      adminControlsAbsent: true,
      adminNavigationLeakFree: true
    });

    expect(evidence.pass).toBe(true);
    expect(isAuthTransitionPass(evidence)).toBe(true);
  });

  it('fails when standard-user monitoring or admin-control absence is not proven', () => {
    const evidence = buildAuthTransitionEvidence({
      startedAsAdministrator: true,
      clearedAuthenticatedState: true,
      establishedStandardUser: true,
      monitorVisible: false,
      rangeControlsAvailable: true,
      populatedReadings: true,
      adminControlsAbsent: true,
      adminNavigationLeakFree: true
    });

    expect(evidence.pass).toBe(false);
    expect(isAuthTransitionPass(evidence)).toBe(false);
  });

  it('fails when any administrator-only personalization control remains visible', () => {
    const evidence = buildAuthTransitionEvidence({
      startedAsAdministrator: true,
      clearedAuthenticatedState: true,
      establishedStandardUser: true,
      monitorVisible: true,
      rangeControlsAvailable: true,
      populatedReadings: true,
      adminControlsAbsent: false,
      adminNavigationLeakFree: true
    });

    expect(evidence.pass).toBe(false);
    expect(isAuthTransitionPass(evidence)).toBe(false);
  });

  it('fails when a standard user can reach an administrator route', () => {
    const evidence = buildAuthTransitionEvidence({
      startedAsAdministrator: true,
      clearedAuthenticatedState: true,
      establishedStandardUser: true,
      monitorVisible: true,
      rangeControlsAvailable: true,
      populatedReadings: true,
      adminControlsAbsent: true,
      adminNavigationLeakFree: false
    });

    expect(evidence.pass).toBe(false);
    expect(isAuthTransitionPass(evidence)).toBe(false);
  });
});
