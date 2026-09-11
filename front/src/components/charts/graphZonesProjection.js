// docs/implementation/graph-semantic-zones-plan.md — the ONLY frontend translator from the
// server-owned `RuleToGraphZones` payload into a chart-ready view-model. Pure, no Chart.js/DOM/
// HTTP access: mirrors graphSeriesProjection.js's role (that file merges history+live series;
// this one normalizes the server's severity bands). Accepts either wire shape:
//   - public bootstrap: `sensor.bands` = [{from, to, severity}]
//   - authenticated `/sensors/{id}/graph-zones`: {zones: [...], boundaries: [{value, severity,
//     bound, rule_id}]}
// The server has already resolved precedence (danger>warning>info>normal, GRAPH-005) and boundary
// inclusivity (GRAPH-004, AlertService's `<=`/`>=`) — this file never re-evaluates AlertRules, it
// only unifies shapes and maps anything unrecognized to 'neutral' (GRAPH-007: no rules/unknown
// severity must never read as green 'normal').

const KNOWN_SEVERITIES = new Set(['danger', 'warning', 'info', 'normal', 'neutral']);

// Mirrors RuleToGraphZones::PRECEDENCE (back/app/Services/Monitoring/RuleToGraphZones.php) plus
// 'neutral' as strictly lowest — an absent rule must never outrank an actual computed severity.
const PRECEDENCE = { danger: 3, warning: 2, info: 1, normal: 0, neutral: -1 };

function normalizeSeverity(value) {
  return KNOWN_SEVERITIES.has(value) ? value : 'neutral';
}

function moreSevere(a, b) {
  return PRECEDENCE[a] >= PRECEDENCE[b] ? a : b;
}

function normalizeRegions(zones) {
  if (!Array.isArray(zones) || !zones.length) {
    // GRAPH-007: no rules ⇒ one neutral region across the whole axis, never green.
    return [{ from: null, to: null, severity: 'neutral' }];
  }

  return zones.map((zone) => ({
    from: zone.from ?? null,
    to: zone.to ?? null,
    severity: normalizeSeverity(zone.severity)
  }));
}

/**
 * Fallback for the public payload, which ships only `{from,to,severity}` bands with no explicit
 * boundary list. An interior edge's own severity is always the higher-precedence of its two
 * neighboring regions: AlertService's inclusive `<=`/`>=` comparison resolves a shared boundary
 * point to whichever side outranks the other, for both min-type and max-type thresholds alike
 * (verified against the worked example in graph-semantic-zones-plan.md). This lets the public
 * chart draw the same boundary line color/position the authenticated `boundaries` array would
 * give, without re-deriving min/max/rule scoping in the client.
 */
function boundariesFromRegions(regions) {
  const boundaries = [];

  for (let i = 0; i < regions.length - 1; i += 1) {
    const value = regions[i].to;
    if (!Number.isFinite(value)) continue;
    boundaries.push({ value, severity: moreSevere(regions[i].severity, regions[i + 1].severity), bound: null });
  }

  return boundaries;
}

function normalizeBoundaries(boundaries, regions) {
  if (Array.isArray(boundaries) && boundaries.length) {
    return boundaries
      .filter((boundary) => Number.isFinite(boundary.value))
      .map((boundary) => ({
        value: boundary.value,
        severity: normalizeSeverity(boundary.severity),
        bound: boundary.bound || null
      }));
  }

  return boundariesFromRegions(regions);
}

/**
 * @param {Array|{zones: Array, boundaries?: Array}|null|undefined} source
 * @returns {{regions: Array<{from: number|null, to: number|null, severity: string}>,
 *            boundaries: Array<{value: number, severity: string, bound: string|null}>,
 *            domainValues: number[]}}
 */
export function buildZonesViewModel(source) {
  const zones = Array.isArray(source) ? source : source?.zones;
  const rawBoundaries = Array.isArray(source) ? null : source?.boundaries;

  const regions = normalizeRegions(zones);
  const boundaries = normalizeBoundaries(rawBoundaries, regions);
  const domainValues = boundaries.map((boundary) => boundary.value).filter(Number.isFinite);

  return { regions, boundaries, domainValues };
}

/**
 * GRAPH-011: the selected sensor's own semantic state (danger/warning/info/normal/neutral),
 * derived only from that sensor's own regions and its own current value.
 *
 * GRAPH-012 / TEST-004: callers must never pass alert-store or email/notification state into this
 * function — there is no such signal in its inputs at all, so a critical alert on another sensor,
 * or a suppressed/failed email for this one, cannot change the result.
 *
 * Exact threshold values are resolved from the server's explicit boundary list first. This is
 * essential for an inclusive min (`value <= min`): its adjacent plot regions remain
 * left-inclusive/right-exclusive for drawable geometry, while the boundary record identifies the
 * alert side. If multiple rules meet at one value, their severities use the same precedence owner
 * as the backend. Non-boundary values use the regular interval lookup below.
 *
 * @param {Array|{zones: Array}|null|undefined} source
 * @param {number|null|undefined} value
 * @returns {string|null} one of 'danger'|'warning'|'info'|'normal'|'neutral', or null when there
 *   is no finite value to classify.
 */
export function sensorSemanticState(source, value) {
  if (!Number.isFinite(value)) return null;

  const { regions, boundaries } = buildZonesViewModel(source);
  const boundaryState = boundaries
    .filter((boundary) => boundary.value === value)
    .reduce((best, boundary) => (
      best === null || PRECEDENCE[boundary.severity] > PRECEDENCE[best]
        ? boundary.severity
        : best
    ), null);

  if (boundaryState !== null) return boundaryState;

  let best = null;

  regions.forEach((region) => {
    const withinLow = region.from === null || value >= region.from;
    const withinHigh = region.to === null || value < region.to;
    if (withinLow && withinHigh && (best === null || PRECEDENCE[region.severity] > PRECEDENCE[best])) {
      best = region.severity;
    }
  });

  return best;
}
