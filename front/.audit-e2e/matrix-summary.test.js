import { describe, expect, it } from 'vitest';
import { buildMatrixSummaryRecord, isPassingMatrixRecord } from './matrix-summary.mjs';

const parsedRun = {
  finalUrl: 'http://127.0.0.1:5173/alert-rules',
  heading: 'Reglas de alerta',
  navError: null,
  overflow: { hasOverflow: false },
  consoleErrors: [],
  pageErrors: [],
  failedRequests: [],
  interaction: { pass: true }
};

describe('responsive matrix summary', () => {
  it('retains final URL, expected marker, and captured heading for page-identity evidence', () => {
    expect(buildMatrixSummaryRecord({
      row: '/alert-rules|admin|320x700|dense|responsive-layout|rules-320|Reglas de alerta',
      exitCode: 0,
      parsed: parsedRun
    })).toMatchObject({
      route: '/alert-rules',
      expectedMarker: 'Reglas de alerta',
      finalUrl: 'http://127.0.0.1:5173/alert-rules',
      heading: 'Reglas de alerta'
    });
  });

  it('fails a clean matrix record if its final page does not match the row marker', () => {
    const record = buildMatrixSummaryRecord({
      row: '/alert-rules|admin|320x700|dense|responsive-layout|rules-320|Reglas de alerta',
      exitCode: 0,
      parsed: { ...parsedRun, heading: 'Iniciar sesión' }
    });

    expect(isPassingMatrixRecord(record)).toBe(false);
  });
});
