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
        try {
            $device = Device::create($data);

            // Registrar el estado inicial

            $device->statusLogs()->create([
                'status' => $data['status'] ?? true,
                'changed_at' => now(),
            ]);
            return $device;
        } catch (\Exception $e) {
            Log::error('DeviceService Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Single command path for a device status transition: update the row, log it, emit
     * `device.status.changed` exactly once (via the domain outbox) — never more than one status
     * log / one event per actual status change, and none at all for a no-op (same-value) request.
     */
    public function changeStatus(Device $device, bool $newStatus): Device
    {
        DB::transaction(function () use ($device, $newStatus): void {
            $previousStatus = (bool) $device->status;

            if ($previousStatus === $newStatus) {
                return;
            }

            $device->update([
                'status' => $newStatus,
                'is_active' => $newStatus,
            ]);

            $device->statusLogs()->create([
                'status' => $newStatus,
                'changed_at' => now(),
            ]);

            $this->recorderInstance()->record('device.status.changed', 'device', $device->id, [
                'device_id' => $device->id,
            ]);
        });

        return $device->refresh();
    }

    private function recorderInstance(): DomainEventRecorder
    {
        return $this->recorder ??= app(DomainEventRecorder::class);
    }
}