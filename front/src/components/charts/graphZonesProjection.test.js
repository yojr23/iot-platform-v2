import { describe, expect, it } from 'vitest';

import { buildZonesViewModel, sensorSemanticState } from './graphZonesProjection';

// Worked example from docs/implementation/graph-semantic-zones-plan.md: warning max=28,
// danger max=30, AlertService's inclusive `>= max` semantics.
const workedExampleBands = [
  { from: null, to: 28, severity: 'normal' },
  { from: 28, to: 30, severity: 'warning' },
  { from: 30, to: null, severity: 'danger' }
];

describe('buildZonesViewModel', () => {
  it('turns a public bands payload into colored regions, preserving server-resolved severities', () => {
    const { regions } = buildZonesViewModel(workedExampleBands);

    expect(regions).toEqual(workedExampleBands);
  });

  it('GRAPH-007 / TEST-005: no rules (empty bands) renders one neutral region, never green/normal', () => {
    expect(buildZonesViewModel([]).regions).toEqual([{ from: null, to: null, severity: 'neutral' }]);
    expect(buildZonesViewModel(null).regions).toEqual([{ from: null, to: null, severity: 'neutral' }]);
    expect(buildZonesViewModel(undefined).regions).toEqual([{ from: null, to: null, severity: 'neutral' }]);
  });

  it('the server-shipped single neutral band ("limits not configured") passes through as neutral, not normal', () => {
    const { regions } = buildZonesViewModel([{ from: null, to: null, severity: 'neutral' }]);
    expect(regions).toEqual([{ from: null, to: null, severity: 'neutral' }]);
  });

  it('an unrecognized/missing severity maps to neutral, never to normal (green)', () => {
    const { regions } = buildZonesViewModel([{ from: null, to: null, severity: 'unknown-future-severity' }]);
    expect(regions[0].severity).toBe('neutral');
  });

  it('derives boundary value+severity from region edges when no explicit boundaries array is given (public payload)', () => {
    const { boundaries } = buildZonesViewModel(workedExampleBands);

    expect(boundaries).toEqual([
      { value: 28, severity: 'warning', bound: null },
      { value: 30, severity: 'danger', bound: null }
    ]);
  });

  it('uses the authenticated boundaries array verbatim (value/severity/bound) when supplied', () => {
    const authPayload = {
      zones: workedExampleBands,
      boundaries: [
        { value: 28, severity: 'warning', bound: 'max', rule_id: 1 },
        { value: 30, severity: 'danger', bound: 'max', rule_id: 2 }
      ]
    };

    const { boundaries } = buildZonesViewModel(authPayload);

    expect(boundaries).toEqual([
      { value: 28, severity: 'warning', bound: 'max' },
      { value: 30, severity: 'danger', bound: 'max' }
    ]);
  });

  it('a boundary outside the observed data range still shows up in domainValues so the Y axis can extend to it', () => {
    // Sensor reads 5..15 but has a danger band starting at 100 far outside that range.
    const { domainValues } = buildZonesViewModel([
      { from: null, to: 100, severity: 'normal' },
      { from: 100, to: null, severity: 'danger' }
    ]);

    expect(domainValues).toContain(100);
  });

  it('TEST-004: the region/boundary color comes only from severity — there is no email/notification field anywhere in the view-model', () => {
    const { regions, boundaries } = buildZonesViewModel(workedExampleBands);
    const serialized = JSON.stringify({ regions, boundaries });

    expect(serialized).not.toMatch(/email|notif/i);
    expect(regions.find((r) => r.severity === 'danger')).toBeTruthy();
  });
});

describe('sensorSemanticState (GRAPH-011)', () => {
  it('classifies a value strictly inside the normal region', () => {
    expect(sensorSemanticState(workedExampleBands, 10)).toBe('normal');
  });

  it('classifies a value in the warning band, inclusive at its own min edge (AlertService >= max semantics)', () => {
    expect(sensorSemanticState(workedExampleBands, 28)).toBe('warning');
    expect(sensorSemanticState(workedExampleBands, 29)).toBe('warning');
  });

  it('classifies a value at/above the danger threshold as danger', () => {
    expect(sensorSemanticState(workedExampleBands, 30)).toBe('danger');
    expect(sensorSemanticState(workedExampleBands, 1000)).toBe('danger');
  });

  it('uses a min boundary to classify the exact inclusive threshold on the alert side', () => {
    const minOnly = {
      zones: [
        { from: null, to: 10, severity: 'danger' },
        { from: 10, to: null, severity: 'normal' }
      ],
      boundaries: [{ value: 10, severity: 'danger', bound: 'min' }]
    };

    expect(sensorSemanticState(minOnly, 9.999)).toBe('danger');
    expect(sensorSemanticState(minOnly, 10)).toBe('danger');
    expect(sensorSemanticState(minOnly, 10.001)).toBe('normal');
  });

  it('no rules configured (neutral) never reads as normal for a real value', () => {
    expect(sensorSemanticState([], 10)).toBe('neutral');
  });

  it('returns null when there is no finite current value to classify', () => {
    expect(sensorSemanticState(workedExampleBands, null)).toBeNull();
    expect(sensorSemanticState(workedExampleBands, undefined)).toBeNull();
    expect(sensorSemanticState(workedExampleBands, Number.NaN)).toBeNull();
  });

  it('precedence danger > warning > info > normal when overlapping regions cover the same value defensively', () => {
    const overlapping = [
      { from: 0, to: 50, severity: 'normal' },
      { from: 10, to: 40, severity: 'info' },
      { from: 15, to: 35, severity: 'warning' },
      { from: 20, to: 30, severity: 'danger' }
    ];

    expect(sensorSemanticState(overlapping, 25)).toBe('danger');
    expect(sensorSemanticState(overlapping, 17)).toBe('warning');
    expect(sensorSemanticState(overlapping, 12)).toBe('info');
    expect(sensorSemanticState(overlapping, 5)).toBe('normal');
  });

  it('GRAPH-012: is a pure function of (bands, value) only — no alerts-store/global-alert input exists to leak in', () => {
    expect(sensorSemanticState.length).toBe(2);
  });
});
