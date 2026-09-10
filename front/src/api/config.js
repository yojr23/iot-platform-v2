import { apiClient } from './client';

export function getRuntimeConfig() {
  return apiClient.get('/config/runtime');
}

export function updateGeneralConfig(payload) {
  return apiClient.put('/config/general', payload);
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

export function getSystemInfo() {
  return apiClient.get('/config/system-info');
}
