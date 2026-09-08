import axios from 'axios';

export const AUTH_TOKEN_KEY = 'iot-platform-v2.auth_token';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api';

export const apiClient = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json'
  },
  withCredentials: false
});

export function getStoredToken() {
  return window.localStorage.getItem(AUTH_TOKEN_KEY);
}

export function setStoredToken(token) {
  window.localStorage.setItem(AUTH_TOKEN_KEY, token);
  // S5-05: single canonical auth lifecycle event with reason metadata — echo.js
  // listens to this and tears down/rebuilds the shared connection once, not twice.
  window.dispatchEvent(new CustomEvent('auth:changed', { detail: { reason: 'login' } }));
}

export function clearStoredToken(reason = 'logout') {
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.dispatchEvent(new CustomEvent('auth:changed', { detail: { reason } }));
}

export function unwrapData(response) {
  const payload = response?.data;
  return payload?.data ?? payload;
}

apiClient.interceptors.request.use((config) => {
  const token = getStoredToken();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // S5-05: one401 → one canonical auth lifecycle event via clearStoredToken('unauthorized').
      // The old pattern dispatched both auth:changed + auth:unauthorized, causing double
      // teardown/resubscribe in echo.js. Now echo.js handles exactly one event per credential
      // transition.
      clearStoredToken('unauthorized');

      if (!window.location.pathname.startsWith('/login')) {
        window.location.assign('/login');
      }
    }

    return Promise.reject(error);
  }
);

export function getApiErrorMessage(error, fallback = 'No se pudo completar la solicitud.') {
  return error.response?.data?.message || error.message || fallback;
}

export function getValidationErrors(error) {
  return error.response?.data?.errors || {};
}
