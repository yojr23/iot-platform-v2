import { describe, expect, it, vi, afterEach } from 'vitest';

import { apiClient } from './client';
import { getSensor } from './sensors';

// Task 2 fix: getSensor(sensorId) used to ignore any AbortSignal passed to it, so an aborted
// request could still resolve and overwrite sensor.value in SensorDetailView. This proves the
// signal now reaches apiClient.get (axios), the same wiring getSensorLatestReadings already had.
describe('getSensor', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('forwards the AbortSignal to apiClient.get', () => {
    const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: {} });
    const controller = new AbortController();

    getSensor(7, { signal: controller.signal });

    expect(getSpy).toHaveBeenCalledWith('/sensors/7', { signal: controller.signal });
  });

  it('works without options (signal undefined)', () => {
    const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: {} });

    getSensor(7);

    expect(getSpy).toHaveBeenCalledWith('/sensors/7', { signal: undefined });
  });
});
