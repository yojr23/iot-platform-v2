import { beforeEach, describe, expect, it, vi } from 'vitest';

const transport = vi.hoisted(() => {
  const handlers = new Map();
  const connection = {
    bind: vi.fn((event, callback) => {
      handlers.set(event, callback);
    }),
    emit(event, ...args) {
      handlers.get(event)?.(...args);
    },
    reset() {
      handlers.clear();
      this.bind.mockClear();
    },
  };

  const Echo = vi.fn(function Echo() {
    return {
      connector: { pusher: { connection } },
      disconnect: vi.fn(),
    };
  });

  return { Echo, connection };
});

vi.mock('laravel-echo', () => ({ default: transport.Echo }));
vi.mock('pusher-js', () => ({ default: {} }));
vi.mock('@/api/client', () => ({ getStoredToken: () => 'test-token' }));

describe('Echo lifecycle manager', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.unstubAllEnvs();
    vi.stubEnv('VITE_PUSHER_APP_KEY', 'test-key');
    vi.stubEnv('VITE_PUSHER_APP_CLUSTER', 'mt1');
    transport.Echo.mockClear();
    transport.connection.reset();
  });

  it('does not notify a listener added while auth notification is in progress', async () => {
    const { getEcho, onResync } = await import('./echo');
    getEcho();

    let firstCalls = 0;
    let replacementCalls = 0;
    let stopFirst;
    stopFirst = onResync(() => {
      firstCalls += 1;
      stopFirst();
      onResync(() => { replacementCalls += 1; });
    });

    transport.connection.emit('connected');
    window.dispatchEvent(new Event('auth:changed'));

    expect(firstCalls).toBe(1);
    expect(replacementCalls).toBe(0);
  });

  it('emits one reconnect only after an actual disconnect', async () => {
    const { getEcho, onResync } = await import('./echo');
    getEcho();
    let reconnects = 0;
    onResync((reason) => {
      if (reason === 'reconnect') reconnects += 1;
    });

    transport.connection.emit('connected');
    transport.connection.emit('connected');
    transport.connection.emit('connected');
    expect(reconnects).toBe(0);

    transport.connection.emit('disconnected');
    transport.connection.emit('connected');
    expect(reconnects).toBe(1);
  });

  it('keeps one visibility listener after Echo is rebuilt', async () => {
    const { disconnectEcho, getEcho, onResync } = await import('./echo');
    getEcho();
    disconnectEcho();
    getEcho();
    let resumptions = 0;
    onResync((reason) => {
      if (reason === 'visibility') resumptions += 1;
    });

    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' });
    document.dispatchEvent(new Event('visibilitychange'));

    expect(resumptions).toBe(1);
  });

  it('immediately reports the current transport state to a late subscriber', async () => {
    const { getEcho, onConnectionStateChange } = await import('./echo');
    getEcho();
    transport.connection.emit('connected');
    const states = [];

    onConnectionStateChange((state) => states.push(state), { immediate: true });

    expect(states).toEqual(['connected']);
  });
});
