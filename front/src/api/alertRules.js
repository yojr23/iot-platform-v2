import { apiClient } from './client';

export function getAlertRules(params = {}) {
  return apiClient.get('/alert-rules', { params });
}

export function getAlertRuleMetadata() {
  return apiClient.get('/alert-rules/create');
}

export function getAlertRule(alertRuleId) {
  return apiClient.get(`/alert-rules/${alertRuleId}`);
}

export function createAlertRule(payload) {
  return apiClient.post('/alert-rules', payload);
}

export function updateAlertRule(alertRuleId, payload) {
  return apiClient.put(`/alert-rules/${alertRuleId}`, payload);
}

export function deleteAlertRule(alertRuleId) {
  return apiClient.delete(`/alert-rules/${alertRuleId}`);
}
