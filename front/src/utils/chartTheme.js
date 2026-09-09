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
