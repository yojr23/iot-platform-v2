import { apiClient } from './client';

export function getUsers(params = {}) {
  return apiClient.get('/users', { params });
}

export function updateUserRole(userId, payload) {
  return apiClient.patch(`/users/${userId}/role`, payload);
}
