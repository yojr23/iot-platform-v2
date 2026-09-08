import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { getStoredToken } from '@/api/client';

let echoInstance = null;
let connectionWired = false; // rebound per Echo instance (reset on disconnectEcho)
let visibilityWired = false; // wired once for the page's lifetime, independent of Echo instance
let wasConnected = false;

// PLAN.md Stage 5 — connection manager built on top of the Echo singleton. Every composable
// that used to bind `echo.connector.pusher.connection` itself (or skip it entirely) now
// shares this one listener set instead, so connection-state handling and ADR-2 lean-V1
// recovery triggers live in exactly one place.
const stateListeners = new Set();
const resyncListeners = new Set();

function notify(listeners, ...args) {
  listeners.forEach((callback) => {
    try {
      callback(...args);
    } catch {
      // one bad listener must not stop delivery to the others
    }
  });
}

// Fires 'reconnect' only on a transition back to connected (never on the very first
// connect) — one of ADR-2's lean V1 recovery triggers. Rebound per Echo instance because
// disconnectEcho() (manual reconnect / auth change) replaces the underlying connection
// object.
function wireConnection(echo) {
  if (connectionWired) {
    return;
  }
  connectionWired = true;

  const connection = echo?.connector?.pusher?.connection;

  if (!connection) {
    return;
  }

  connection.bind('connected', () => {
    const isReconnect = wasConnected === false;
    wasConnected = true;
    notify(stateListeners, 'connected');
    if (isReconnect) {
      notify(resyncListeners, 'reconnect');
    }
  });
  connection.bind('disconnected', () => {
    wasConnected = false;
    notify(stateListeners, 'disconnected');
  });
  connection.bind('unavailable', () => {
    wasConnected = false;
    notify(stateListeners, 'unavailable');
  });
  connection.bind('error', (connectionError) => {
    notify(stateListeners, 'error', connectionError);
  });
}

// 'visibility' (tab becomes visible again — mobile resume) is the other ADR-2 lean V1
// recovery trigger. Wired exactly once for the page's lifetime: document doesn't change
// across Echo instance rebuilds, so — unlike wireConnection — this must NOT be re-armed on
// every disconnectEcho(), or repeated auth changes/reconnects would stack duplicate
// document-level listeners and fire the same resync multiple times per tab-visible event.
function wireVisibility() {
  if (visibilityWired || typeof document === 'undefined') {
    return;
  }
  visibilityWired = true;

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      notify(resyncListeners, 'visibility');
    }
  });
}

function handleAuthChange() {
  // Credential change (login/logout/token refresh) or a 401 both mean any inaccessible
  // realtime state must be cleared: tear down the shared Echo instance so the next getEcho()
  // rebuilds the Pusher auth headers from the current token, then tell subscribers to redo
  // their subscription against the fresh instance (PLAN.md Stage 5.2/5.3).
  disconnectEcho();
  notify(resyncListeners, 'auth');
}

if (typeof window !== 'undefined') {
  // client.js dispatches these as plain DOM events (not a direct import) to avoid a
  // client.js <-> echo.js import cycle — echo.js already imports getStoredToken from
  // client.js.
  window.addEventListener('auth:changed', handleAuthChange);
  window.addEventListener('auth:unauthorized', handleAuthChange);
}

function booleanEnv(value, defaultValue = true) {
  if (value === undefined || value === null || value === '') {
    return defaultValue;
  }

  return !['false', '0', 'no'].includes(String(value).toLowerCase());
}

function numberEnv(value) {
  const numeric = Number(value);
  return Number.isFinite(numeric) && numeric > 0 ? numeric : undefined;
}

export function getEchoConfig() {
  const key = import.meta.env.VITE_PUSHER_APP_KEY;
  const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;
  const forceTLS = booleanEnv(import.meta.env.VITE_PUSHER_FORCE_TLS, true);
  const host = import.meta.env.VITE_PUSHER_HOST;
  const port = numberEnv(import.meta.env.VITE_PUSHER_PORT);
  const scheme = import.meta.env.VITE_PUSHER_SCHEME || (forceTLS ? 'https' : 'http');
  const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api';

  if (!key || (!cluster && !host)) {
    return null;
  }

  const config = {
    broadcaster: 'pusher',
    key,
    cluster: cluster || 'mt1',
    forceTLS,
    encrypted: forceTLS,
    authEndpoint: `${apiBaseUrl.replace(/\/$/, '')}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${getStoredToken() || ''}`
      }
    }
  };

  if (host) {
    config.wsHost = host;
    config.wsPort = port;
    config.wssPort = port;
    config.forceTLS = scheme === 'https';
  }

  // PLAN.md Stage 5.2 / ownership F1: enabledTransports must be set on every config path,
  // not just the custom-host one.
  config.enabledTransports = ['ws', 'wss'];

  return config;
}

export function getEcho() {
  if (echoInstance) {
    return echoInstance;
  }

  const config = getEchoConfig();

  if (!config) {
    return null;
  }

  if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
  }

  echoInstance = new Echo(config);
  wireConnection(echoInstance);
  wireVisibility();
  return echoInstance;
}

export function createEcho() {
  return getEcho();
}

export function disconnectEcho() {
  if (echoInstance) {
    echoInstance.disconnect();
  }

  echoInstance = null;
  connectionWired = false;
  wasConnected = false;
}

/**
 * Subscribe to connection-state transitions ('connected' | 'disconnected' | 'unavailable' |
 * 'error'). Returns an unsubscribe function.
 */
export function onConnectionStateChange(callback) {
  stateListeners.add(callback);
  return () => stateListeners.delete(callback);
}

/**
 * PLAN.md Stage 5.3 / ADR-2 lean V1: subscribe to lifecycle-triggered recovery events
 * ('reconnect' | 'visibility' | 'auth'). Fired at most once per qualifying transition —
 * never a periodic loop. Callback is responsible for its own bounded, one-shot snapshot.
 */
export function onResync(callback) {
  resyncListeners.add(callback);
  return () => resyncListeners.delete(callback);
}
