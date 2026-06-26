import { apiClient } from './client';

export function getLabs(params = {}) {
  return apiClient.get('/labs', { params });
}

export function createLab(payload) {
  return apiClient.post('/labs', payload);
}

export function updateLab(id, payload) {
  return apiClient.put(`/labs/${id}`, payload);
}

export function deleteLab(id) {
  return apiClient.delete(`/labs/${id}`);
}

export function getSensorTypes(params = {}) {
  return apiClient.get('/sensor-types', { params });
}

export function createSensorType(payload) {
  return apiClient.post('/sensor-types', payload);
}

export function updateSensorType(id, payload) {
  return apiClient.put(`/sensor-types/${id}`, payload);
}

export function deleteSensorType(id) {
  return apiClient.delete(`/sensor-types/${id}`);
}

export function getDeviceTypes(params = {}) {
  return apiClient.get('/device-types', { params });
}

export function createDeviceType(payload) {
  return apiClient.post('/device-types', payload);
}

export function updateDeviceType(id, payload) {
  return apiClient.put(`/device-types/${id}`, payload);
}

export function deleteDeviceType(id) {
  return apiClient.delete(`/device-types/${id}`);
}
