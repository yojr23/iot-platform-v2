// Pure contract helpers for the mocked administrator -> standard-user transition.
export function buildAuthTransitionEvidence({
  startedAsAdministrator,
  clearedAuthenticatedState,
  establishedStandardUser,
  monitorVisible,
  rangeControlsAvailable,
  populatedReadings,
  adminControlsAbsent,
  adminNavigationLeakFree
}) {
  return {
    startedAsAdministrator: Boolean(startedAsAdministrator),
    clearedAuthenticatedState: Boolean(clearedAuthenticatedState),
    establishedStandardUser: Boolean(establishedStandardUser),
    monitorVisible: Boolean(monitorVisible),
    rangeControlsAvailable: Boolean(rangeControlsAvailable),
    populatedReadings: Boolean(populatedReadings),
    adminControlsAbsent: Boolean(adminControlsAbsent),
    adminNavigationLeakFree: Boolean(adminNavigationLeakFree),
    pass: Boolean(
      startedAsAdministrator &&
      clearedAuthenticatedState &&
      establishedStandardUser &&
      monitorVisible &&
      rangeControlsAvailable &&
      populatedReadings &&
      adminControlsAbsent &&
      adminNavigationLeakFree
    )
  };
}

export function isAuthTransitionPass(evidence) {
  return evidence?.pass === true &&
    evidence.startedAsAdministrator === true &&
    evidence.clearedAuthenticatedState === true &&
    evidence.establishedStandardUser === true &&
    evidence.monitorVisible === true &&
    evidence.rangeControlsAvailable === true &&
    evidence.populatedReadings === true &&
    evidence.adminControlsAbsent === true &&
    evidence.adminNavigationLeakFree === true;
}
