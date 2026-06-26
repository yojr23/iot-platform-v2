import { apiClient } from './client';

export function getSensors(params = {}) {
  return apiClient.get('/sensors', { params });
}

export function getSensor(sensorId) {
  return apiClient.get(`/sensors/${sensorId}`);
}

export function createSensor(payload) {
  return apiClient.post('/sensors', payload);
}

export function updateSensor(sensorId, payload) {
  return apiClient.put(`/sensors/${sensorId}`, payload);
}

export function deleteSensor(sensorId) {
  return apiClient.delete(`/sensors/${sensorId}`);
}

export function getSensorLatestReadings(sensorId, params = {}) {
  return apiClient.get(`/sensors/${sensorId}/latest-readings`, { params });
}

export function getSensorReadings(sensorId, params = {}) {
  return apiClient.get(`/sensors/${sensorId}/readings`, { params });
}

export function exportSensorReadings(sensorId, params = {}) {
  return apiClient.get(`/sensors/${sensorId}/readings/export`, { params });
}
