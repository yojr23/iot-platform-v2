import { describe, expect, it } from 'vitest';

import { buildSensorChartViewModel } from './sensorChartViewModel';

describe('buildSensorChartViewModel', () => {
  it('returns empty labels/series and null stats for no readings', () => {
    const viewModel = buildSensorChartViewModel([], { unit: 'C' });

    expect(viewModel.labels).toEqual([]);
    expect(viewModel.series).toEqual([]);
    expect(viewModel.stats).toBeNull();
    expect(viewModel.lastObservedAt).toBe('');
    expect(viewModel.unit).toBe('C');
  });

  it('reverses newest-first readings into chronological order for the chart', () => {
    const readings = [
      { value: 30, reading_time: '2026-01-01T10:02:00Z' },
      { value: 20, reading_time: '2026-01-01T10:01:00Z' },
      { value: 10, reading_time: '2026-01-01T10:00:00Z' }
    ];

    const viewModel = buildSensorChartViewModel(readings, { unit: 'C' });

    expect(viewModel.series).toEqual([10, 20, 30]);
  });

  it('computes min/max/mean/count for the returned set', () => {
    const readings = [
      { value: 30, reading_time: '2026-01-01T10:02:00Z' },
      { value: 20, reading_time: '2026-01-01T10:01:00Z' },
      { value: 10, reading_time: '2026-01-01T10:00:00Z' }
    ];

    const viewModel = buildSensorChartViewModel(readings, { unit: 'C' });

    expect(viewModel.stats).toEqual({ min: 10, max: 30, mean: 20, count: 3 });
  });

  it('reports the most recent reading timestamp as lastObservedAt', () => {
    const readings = [
      { value: 30, reading_time: '2026-01-01T10:02:00Z' },
      { value: 20, reading_time: '2026-01-01T10:01:00Z' }
    ];

    const viewModel = buildSensorChartViewModel(readings, { unit: 'C' });

    expect(viewModel.lastObservedAt).toBe('2026-01-01T10:02:00Z');
  });

  it('falls back to created_at when reading_time is missing', () => {
    const readings = [{ value: 5, created_at: '2026-02-02T00:00:00Z' }];

    const viewModel = buildSensorChartViewModel(readings, { unit: '' });

    expect(viewModel.lastObservedAt).toBe('2026-02-02T00:00:00Z');
    expect(viewModel.labels).toHaveLength(1);
  });
});
