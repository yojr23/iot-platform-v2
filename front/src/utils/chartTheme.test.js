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
    expect(tokens.warning.line).toBe('#d97706');
    expect(tokens.info.line).toBe('#2563eb');
    expect(tokens.normal.line).toBe('#16a34a');
    expect(tokens.neutral.line).toBe('#94a3b8');
    expect(tokens.info.line).not.toBe(tokens.normal.line);
  });

  it('reads overridden --sinoa-zone-* CSS custom properties from :root when present', () => {
    document.documentElement.style.setProperty('--sinoa-zone-danger', '#ff0000');

    const tokens = resolveZoneTokens();

    expect(tokens.danger.line).toBe('#ff0000');
    expect(tokens.danger.fill).toBe('rgba(255, 0, 0, 0.16)');
  });
});
