import { apiClient } from './client';

export function login(credentials) {
  return apiClient.post('/auth/login', credentials);
}

export function register(payload) {
  return apiClient.post('/auth/register', payload);
}

export function me() {
  return apiClient.get('/auth/me');
}

export function logout() {
  return apiClient.post('/auth/logout');
}

export function forgotPassword(payload) {
  return apiClient.post('/auth/forgot-password', payload);
}

export function resetPassword(payload) {
  return apiClient.post('/auth/reset-password', payload);
}

export function verifyEmail(id, hash, params = {}) {
  return apiClient.get(`/auth/verify-email/${id}/${hash}`, { params });
}

export function resendVerification() {
  return apiClient.post('/auth/resend-verification');
}
