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
