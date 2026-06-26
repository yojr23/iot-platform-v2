import { apiClient } from './client';

export function getMetrics() {
  return apiClient.get('/metrics');
}

