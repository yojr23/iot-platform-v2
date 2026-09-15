import { isPassingAuditResult } from './result-policy.mjs';

export function parseMatrixRow(row) {
  const [route, role, viewport, mode, interact, name, expectedMarker] = row.split('|');
  return {
    route,
    role,
    viewport,
    mode,
    interact,
    name,
    expectedMarker: expectedMarker || null
  };
}

export function buildMatrixSummaryRecord({ row, exitCode, parsed }) {
  const matrix = typeof row === 'string' ? parseMatrixRow(row) : row;

  return {
    ...matrix,
    exitCode,
    navError: parsed ? parsed.navError : 'PARSE_FAILURE',
    hasOverflow: parsed ? parsed.overflow?.hasOverflow ?? null : null,
    consoleErrors: parsed ? parsed.consoleErrors?.length ?? null : null,
    pageErrors: parsed ? parsed.pageErrors?.length ?? null : null,
    failedRequests: parsed ? parsed.failedRequests?.length ?? null : null,
    interaction: parsed ? parsed.interaction : null,
    // Keep identity evidence in the consolidated artifact so a clean transport result cannot
    // conceal a redirect, a wrong route, or the wrong rendered surface.
    finalUrl: parsed ? parsed.finalUrl : null,
    heading: parsed ? parsed.heading : null
  };
}

export function isPassingMatrixRecord(record) {
  return isPassingAuditResult(record, { expectedMarker: record.expectedMarker });
}
