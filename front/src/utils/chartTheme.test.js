import { afterEach, describe, expect, it } from 'vitest';

import { resolveChartTokens } from './chartTheme';

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
