import { apiClient } from './client';

export function getDevices({ signal, ...params } = {}) {
  return apiClient.get('/devices', { params, signal });
}

export function getDeviceStatusSnapshot(params = {}) {
  return apiClient.get('/devices/status-snapshot', { params });
}

export function getDevice(deviceId) {
  return apiClient.get(`/devices/${deviceId}`);
}

export function createDevice(payload) {
  return apiClient.post('/devices', payload);
}

export function updateDevice(deviceId, payload) {
  return apiClient.put(`/devices/${deviceId}`, payload);
}

export function deleteDevice(deviceId) {
  return apiClient.delete(`/devices/${deviceId}`);
}

export function updateDeviceStatus(deviceId, payload) {
  return apiClient.post(`/devices/${deviceId}/status`, payload);
}

export function getDeviceSensors(deviceId) {
  return apiClient.get(`/devices/${deviceId}/sensor-list`);
}
