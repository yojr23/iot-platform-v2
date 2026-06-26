import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { getStoredToken } from '@/api/client';

let echoInstance = null;

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
    config.enabledTransports = ['ws', 'wss'];
  }

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
}
