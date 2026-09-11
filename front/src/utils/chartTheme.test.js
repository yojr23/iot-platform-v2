import { afterEach, describe, expect, it } from 'vitest';

import { resolveChartTokens, resolveZoneTokens } from './chartTheme';

describe('resolveChartTokens', () => {
  afterEach(() => {
    document.documentElement.style.cssText = '';
  });

  it('falls back to the main.scss --app-blue/--app-muted/--app-border tokens when unset', () => {
    const tokens = resolveChartTokens();

    expect(tokens.line).toBe('#1d4ed8');
    expect(tokens.muted).toBe('#5f6f86');
    expect(tokens.grid).toBe('#cbd8ea');
    expect(tokens.fill).toBe('rgba(29, 78, 216, 0.14)');
  });

  it('reads overridden CSS custom properties from :root when present', () => {
    document.documentElement.style.setProperty('--app-blue', '#ff0000');

    const tokens = resolveChartTokens();

    expect(tokens.line).toBe('#ff0000');
    expect(tokens.fill).toBe('rgba(255, 0, 0, 0.14)');
  });
});

describe('resolveZoneTokens', () => {
  afterEach(() => {
    document.documentElement.style.cssText = '';
  });

  it('falls back to the lab-blue.css --sinoa-zone-* hexes when unset, info stays blue not green', () => {
    const tokens = resolveZoneTokens();

    expect(tokens.danger.line).toBe('#dc2626');
    expect(tokens.warning.line).toBe('#f59e0b');
    expect(tokens.info.line).toBe('#2563eb');
    expect(tokens.normal.line).toBe('#22c55e');
    expect(tokens.neutral.line).toBe('#94a3b8');
    expect(tokens.danger.fill).toBe('rgba(220, 38, 38, 0.12)');
    expect(tokens.warning.fill).toBe('rgba(245, 158, 11, 0.14)');
    expect(tokens.normal.fill).toBe('rgba(34, 197, 94, 0.12)');
    expect(tokens.info.fill).toBe('rgba(37, 99, 235, 0.16)');
    expect(tokens.neutral.fill).toBe('rgba(148, 163, 184, 0.16)');
    expect(tokens.info.line).not.toBe(tokens.normal.line);
  });

  it('reads overridden --sinoa-zone-* CSS custom properties from :root when present', () => {
    document.documentElement.style.setProperty('--sinoa-zone-danger', '#ff0000');

    const tokens = resolveZoneTokens();

    expect(tokens.danger.line).toBe('#ff0000');
    expect(tokens.danger.fill).toBe('rgba(255, 0, 0, 0.12)');
  });
});
