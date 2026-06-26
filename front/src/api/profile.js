import { apiClient } from './client';

export function getProfile() {
  return apiClient.get('/profile');
}
