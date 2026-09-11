import { apiClient } from './client';

export function getMetrics(config = {}) {
  return apiClient.get('/metrics', config);
}

