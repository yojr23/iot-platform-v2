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
}

export function clearStoredToken() {
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
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
      clearStoredToken();
      window.dispatchEvent(new CustomEvent('auth:unauthorized'));

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
