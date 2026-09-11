// Reuses the SINOA/Lab Blue design tokens defined as CSS custom properties
// in `src/assets/styles/main.scss` (:root). Fallback hex values below must
// stay in sync with that stylesheet; they exist because canvas-based
// Chart.js rendering (and unit tests without the stylesheet loaded) can't
// resolve CSS custom properties otherwise.
const FALLBACK_TOKENS = Object.freeze({
  line: '#1d4ed8', // --app-blue
  muted: '#5f6f86', // --app-muted
  grid: '#cbd8ea' // --app-border
});

function readCssVar(name, fallback) {
  if (typeof document === 'undefined') {
    return fallback;
  }

  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

function hexToRgba(hex, alpha) {
  const match = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);

  if (!match) {
    return `rgba(29, 78, 216, ${alpha})`;
  }

  const [r, g, b] = match.slice(1).map((part) => parseInt(part, 16));
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export function resolveChartTokens() {
  const line = readCssVar('--app-blue', FALLBACK_TOKENS.line);
  const muted = readCssVar('--app-muted', FALLBACK_TOKENS.muted);
  const grid = readCssVar('--app-border', FALLBACK_TOKENS.grid);

  return {
    line,
    fill: hexToRgba(line, 0.14),
    muted,
    grid
  };
}

// docs/implementation/graph-semantic-zones-plan.md — plot-area semantic zone colors. Reuses the
// SINOA `--sinoa-zone-*` tokens defined in `src/assets/styles/lab-blue.css` (danger/warning/info/
// normal/neutral); fallback hexes below mirror those exact values for canvas rendering and for
// tests that don't load the stylesheet. `info` stays a distinct brand/blue tint, never green;
// `neutral` ("limits not configured") stays gray, never green either.
const ZONE_FALLBACKS = Object.freeze({
  danger: '#dc2626',
  warning: '#d97706',
  info: '#2563eb',
  normal: '#16a34a',
  neutral: '#94a3b8'
});

const ZONE_CSS_VARS = Object.freeze({
  danger: '--sinoa-zone-danger',
  warning: '--sinoa-zone-warning',
  info: '--sinoa-zone-info',
  normal: '--sinoa-zone-normal',
  neutral: '--sinoa-zone-neutral'
});

export function resolveZoneTokens() {
  return Object.fromEntries(
    Object.entries(ZONE_CSS_VARS).map(([severity, cssVar]) => {
      const line = readCssVar(cssVar, ZONE_FALLBACKS[severity]);
      return [severity, { line, fill: hexToRgba(line, 0.16) }];
    })
  );
}
