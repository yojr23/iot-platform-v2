import { describe, expect, it } from 'vitest';
import { formatTelemetryDate } from './formatters';

describe('formatTelemetryDate', () => {
  it('renders telemetry readings in explicit UTC regardless of browser timezone', () => {
    const formatted = formatTelemetryDate('2026-01-01T05:02:00Z');

    expect(formatted).toContain('05:02');
    expect(formatted).toContain('UTC');
  });
});
