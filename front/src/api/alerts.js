import { apiClient } from './client';

export function getAlerts(params = {}) {
  return apiClient.get('/alerts', { params });
}

export function getAlert(alertId) {
  return apiClient.get(`/alerts/${alertId}`);
}

export function getUnresolvedAlerts(params = {}) {
  return apiClient.get('/alerts/unresolved', { params });
}

export function getActiveAlerts(params = {}) {
  return apiClient.get('/alerts/active', { params });
}

export function resolveAlert(alertId) {
  return apiClient.patch(`/alerts/${alertId}/resolve`);
}

export function resolveAllAlerts() {
  return apiClient.post('/alerts/resolve-all');
}
