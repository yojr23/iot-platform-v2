import { apiClient } from './client';

export function getPublicConfig() {
  return apiClient.get('/config/public');
}

export function getAlertConfig() {
  return apiClient.get('/config/alerts');
}

export function updateAlertConfig(payload) {
  return apiClient.put('/config/alerts', payload);
}

export function getEmailConfig() {
  return apiClient.get('/config/email');
}

export function updateEmailConfig(payload) {
  return apiClient.put('/config/email', payload);
}

export function testEmailConfig(payload) {
  return apiClient.post('/config/email/test', payload);
}
