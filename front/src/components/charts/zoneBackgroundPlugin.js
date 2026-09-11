// docs/implementation/graph-semantic-zones-plan.md — Chart.js plugin that paints the plot-area
// background from the regions/boundaries `buildZonesViewModel` (graphZonesProjection.js) already
// produced, and draws boundary lines + value labels. This is the ONLY place that touches
// chart.ctx/chart.chartArea/chart.scales for zones — SensorReadingChart.vue just registers it and
// hands it options, it never draws directly.
//
// The two geometry helpers below take a scale-shaped `{ getPixelForValue }` object rather than a
// real Chart.js Scale instance, so they're unit-testable without a canvas (jsdom in this repo has
// no canvas backend — see SensorReadingChart.test.js's "can't acquire context" console output).

export function computeRegionRect(region, scale, chartArea) {
  const clamp = (px) => Math.min(Math.max(px, chartArea.top), chartArea.bottom);
  const top = region.to == null ? chartArea.top : scale.getPixelForValue(region.to);
  const bottom = region.from == null ? chartArea.bottom : scale.getPixelForValue(region.from);
  return { top: clamp(top), bottom: clamp(bottom) };
}

export function computeBoundaryY(value, scale, chartArea) {
  const y = scale.getPixelForValue(value);
  return y >= chartArea.top && y <= chartArea.bottom ? y : null;
}

export const zoneBackgroundPlugin = {
  id: 'zoneBackground',
  beforeDraw(chart, _args, opts) {
    const regions = opts?.regions || [];
    const tokens = opts?.tokens || {};
    const { ctx, chartArea, scales } = chart;
    if (!chartArea || !scales?.y || !regions.length) return;

    ctx.save();
    regions.forEach((region) => {
      const { top, bottom } = computeRegionRect(region, scales.y, chartArea);
      if (bottom <= top) return;
      ctx.fillStyle = tokens[region.severity]?.fill || tokens.neutral?.fill || 'rgba(148, 163, 184, 0.16)';
      ctx.fillRect(chartArea.left, top, chartArea.right - chartArea.left, bottom - top);
    });
    ctx.restore();
  },
  afterDraw(chart, _args, opts) {
    const boundaries = opts?.boundaries || [];
    const tokens = opts?.tokens || {};
    const { ctx, chartArea, scales } = chart;
    if (!chartArea || !scales?.y || !boundaries.length) return;

    ctx.save();
    ctx.font = '10px Inter, sans-serif';
    ctx.textBaseline = 'bottom';
    ctx.textAlign = 'right';
    boundaries.forEach((boundary) => {
      const y = computeBoundaryY(boundary.value, scales.y, chartArea);
      if (y === null) return;
      const color = tokens[boundary.severity]?.line || tokens.neutral?.line || '#94a3b8';

      ctx.strokeStyle = color;
      ctx.setLineDash([4, 3]);
      ctx.beginPath();
      ctx.moveTo(chartArea.left, y);
      ctx.lineTo(chartArea.right, y);
      ctx.stroke();
      ctx.setLineDash([]);

      ctx.fillStyle = color;
      ctx.fillText(String(boundary.value), chartArea.right, y - 2);
    });
    ctx.restore();
  }
};
