export function hasInteractionFailure(interaction) {
  return Boolean(interaction?.error) || interaction?.pass === false;
}

export function isCleanResult(result) {
  return result.exitCode === 0 &&
    !result.navError &&
    result.hasOverflow === false &&
    result.consoleErrors === 0 &&
    result.pageErrors === 0 &&
    result.failedRequests === 0 &&
    !hasInteractionFailure(result.interaction);
}

/**
 * Verify the browser landed on the intended page:
 *  - finalUrl pathname matches the requested route (or is a sub-path for detail views)
 *  - no denied=permission redirect occurred
 *  - expected page marker (heading text or selector) is present in the DOM
 */
export function isPageCorrect(result, { expectedMarker } = {}) {
  if (!result.finalUrl) return false;

  const finalPathname = new URL(result.finalUrl).pathname;

  // denied=permission means the router rejected the user — fail unless denial was the test goal
  if (result.finalUrl.includes('denied=permission')) return false;

  // Route must match: exact or prefix (for /devices/1 style detail routes)
  if (result.route && !finalPathname.startsWith(result.route)) return false;

  // If a marker is expected, the heading captured by run.mjs must contain it
  if (expectedMarker) {
    if (!result.heading) return false;
    if (!result.heading.toLowerCase().includes(expectedMarker.toLowerCase())) return false;
  }

  return true;
}

export function isPassingAuditResult(result, { expectedMarker } = {}) {
  return isCleanResult(result) && isPageCorrect(result, { expectedMarker });
}
