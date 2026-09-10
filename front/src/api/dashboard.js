import { apiClient } from './client';

export function getDashboardMetrics() {
  return apiClient.get('/dashboard/metrics');
}

export function getDashboardPreferences() {
  return apiClient.get('/dashboard/preferences');
}

export function updateDashboardPreferences(payload) {
  return apiClient.put('/dashboard/preferences', payload);
}
