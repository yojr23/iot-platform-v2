import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createLogger } from './logger';

describe('createLogger', () => {
  beforeEach(() => {
    vi.stubEnv('VITE_LOG_LEVEL', 'debug');
  });

  afterEach(() => {
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
  });

  it('prefixes messages with the source tag', () => {
    const spy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const log = createLogger('TestSource');

    log.warn('something happened');

    expect(spy).toHaveBeenCalledWith('[TestSource]', 'something happened');
  });

  it('passes additional arguments through', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const log = createLogger('X');

    log.error('fail', { code: 500 }, 'extra');

    expect(spy).toHaveBeenCalledWith('[X]', 'fail', { code: 500 }, 'extra');
  });

  it('suppresses debug when VITE_LOG_LEVEL is warn', () => {
    vi.stubEnv('VITE_LOG_LEVEL', 'warn');
    const spy = vi.spyOn(console, 'debug').mockImplementation(() => {});
    const log = createLogger('X');

    log.debug('nope');

    expect(spy).not.toHaveBeenCalled();
  });

  it('emits debug when VITE_LOG_LEVEL is debug', () => {
    const spy = vi.spyOn(console, 'debug').mockImplementation(() => {});
    const log = createLogger('X');

    log.debug('yes');

    expect(spy).toHaveBeenCalledWith('[X]', 'yes');
  });
});
