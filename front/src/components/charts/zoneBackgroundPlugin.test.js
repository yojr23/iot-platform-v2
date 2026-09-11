import { describe, expect, it } from 'vitest';

import { computeBoundaryY, computeRegionRect } from './zoneBackgroundPlugin';

// A fake linear scale: value 0 -> pixel 100 (bottom), value 100 -> pixel 0 (top). Chart.js' real Y
// scale is inverted the same way (higher value = smaller pixel), which is the behavior these pure
// helpers must handle without touching an actual canvas/Chart.js Scale instance.
const chartArea = { top: 0, bottom: 100, left: 0, right: 200 };
const linearScale = { getPixelForValue: (value) => 100 - value };

describe('computeRegionRect', () => {
  it('maps a bounded region to its pixel rect via the scale', () => {
    const rect = computeRegionRect({ from: 20, to: 80 }, linearScale, chartArea);
    expect(rect).toEqual({ top: 20, bottom: 80 });
  });

  it('clamps an unbounded lower edge (from: null) to the chart area bottom', () => {
    const rect = computeRegionRect({ from: null, to: 80 }, linearScale, chartArea);
    expect(rect).toEqual({ top: 20, bottom: 100 });
  });

  it('clamps an unbounded upper edge (to: null) to the chart area top', () => {
    const rect = computeRegionRect({ from: 20, to: null }, linearScale, chartArea);
    expect(rect).toEqual({ top: 0, bottom: 80 });
  });

  it('clamps a region entirely outside the visible chart area to a zero-height rect', () => {
    // A boundary far above the axis domain should never produce a rect above the chart area.
    const rect = computeRegionRect({ from: 500, to: 600 }, linearScale, chartArea);
    expect(rect.top).toBeGreaterThanOrEqual(chartArea.top);
    expect(rect.bottom).toBeLessThanOrEqual(chartArea.bottom);
  });
});

describe('computeBoundaryY', () => {
  it('returns the pixel y for a value inside the chart area', () => {
    expect(computeBoundaryY(30, linearScale, chartArea)).toBe(70);
  });

  it('returns null for a value whose pixel falls outside the chart area (nothing drawn off-canvas)', () => {
    expect(computeBoundaryY(500, linearScale, chartArea)).toBeNull();
  });
});
