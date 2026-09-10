<?php
// app/Services/DeviceService.php
namespace App\Services;

use App\Models\Device;
use App\Services\Ingestion\DomainEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 4.2, G0D row B5 — expanded (not replaced) with the device *status transition*
 * owner. `createDevice()` is unchanged.
 *
 * Existing code reused: `Device::update()` + `statusLogs()->create()` — the exact calls
 * `Api\DeviceApiController::update()` and the Blade `DeviceController::toggleStatus()` already made
 * ad hoc; centralized here instead of duplicated per controller.
 * Existing owner retired/delegated: `Api\DeviceApiController::updateStatus()` (mutated status/
 * is_active with no status log and no event — audit RC2/RC3) and `updateStatus()`'s status-only
 * branch of `update()`, plus the Blade `toggleStatus()` (wrote a status log but no event), no
 * longer mutate `Device` status directly — both API and Blade now call `changeStatus()`.
 * Compatibility window: none — one status-transition owner from this stage on.
 */
class DeviceService
{
    // ponytail: nullable + lazy container fallback (not a required constructor arg) only so the
    // pre-existing `new DeviceService()` call in tests/Unit/DeviceServiceTest.php (which never
    // touches changeStatus()) keeps working without DI. Every real caller (controllers) gets the
    // real recorder injected by the container as normal.
    public function __construct(private ?DomainEventRecorder $recorder = null)
    {
    }

    public function createDevice(array $data)
    {
        Log::info('DeviceService:createDevice entry', ['serial_number' => $data['serial_number'] ?? null, 'name' => $data['name'] ?? null]);

        $startTime = microtime(true);

        $device = Device::create($data);

        $device->statusLogs()->create([
            'status' => $data['status'] ?? true,
            'changed_at' => now(),
        ]);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('DeviceService:createDevice completed', ['device_id' => $device->id, 'duration_ms' => $durationMs]);

        if ($durationMs > 100) {
            Log::warning('DeviceService:createDevice slow query', ['duration_ms' => $durationMs, 'table' => 'devices']);
        }

        return $device;
    }

    /**
     * Single command path for a device status transition: update the row, log it, emit
     * `device.status.changed` exactly once (via the domain outbox) — never more than one status
     * log / one event per actual status change, and none at all for a no-op (same-value) request.
     */
    public function changeStatus(Device $device, bool $newStatus): Device
    {
        Log::info('DeviceService:changeStatus entry', [
            'device_id' => $device->id,
            'new_status' => $newStatus,
        ]);

        $startTime = microtime(true);

        DB::transaction(function () use ($device, $newStatus): void {
            $lockedDevice = Device::query()->lockForUpdate()->findOrFail($device->id);
            $previousStatus = (bool) $lockedDevice->status;

            if ($previousStatus === $newStatus) {
                Log::info('DeviceService:changeStatus no-op (same status)', [
                    'device_id' => $device->id,
                    'current_status' => $previousStatus,
                    'requested_status' => $newStatus,
                ]);
                return;
            }

            $changedAt = now();

            $lockedDevice->update([
                'status' => $newStatus,
                'is_active' => $newStatus,
            ]);

            $lockedDevice->statusLogs()->create([
                'status' => $newStatus,
                'changed_at' => $changedAt,
            ]);

            $this->recorderInstance()->record('device.status.changed', 'device', $lockedDevice->id, [
                'device_id' => $lockedDevice->id,
                'status' => (bool) $lockedDevice->status,
                'is_active' => (bool) $lockedDevice->is_active,
                'changed_at' => $changedAt->toIso8601String(),
            ]);
        });

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('DeviceService:changeStatus completed', [
            'device_id' => $device->id,
            'new_status' => $newStatus,
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('DeviceService:changeStatus slow transaction', ['duration_ms' => $durationMs, 'device_id' => $device->id]);
        }

        return $device->refresh();
    }

    private function recorderInstance(): DomainEventRecorder
    {
        return $this->recorder ??= app(DomainEventRecorder::class);
    }
}
