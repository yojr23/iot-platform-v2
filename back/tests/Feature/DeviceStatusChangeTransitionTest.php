<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceStatusLog;
use App\Models\DomainEventOutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Stage 4.2 (audit RC2/RC3): `DeviceApiController::updateStatus()` used to mutate
 * status/is_active directly with NO status log and NO event at all. Both the API and the Blade
 * device controllers must now converge on `DeviceService::changeStatus()`, which writes exactly one
 * `device_status_logs` row and exactly one `device.status.changed` domain-outbox row per real
 * status change (and none at all for a same-value no-op request).
 */
class DeviceStatusChangeTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_update_status_writes_one_log_and_one_outbox_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/status", ['status' => false])
            ->assertOk()
            ->assertJsonPath('device.status', false);

        $this->assertSame(false, (bool) $device->fresh()->status);
        $this->assertSame(1, DeviceStatusLog::query()->where('device_id', $device->id)->count());
        $this->assertSame(1, DomainEventOutbox::query()
            ->where('event_type', 'device.status.changed')
            ->where('aggregate_id', (string) $device->id)
            ->count());
    }

    public function test_api_update_status_same_value_is_a_noop_no_log_no_event(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/status", ['status' => true])
            ->assertOk();

        $this->assertSame(0, DeviceStatusLog::query()->where('device_id', $device->id)->count());
        $this->assertSame(0, DomainEventOutbox::query()->where('aggregate_id', (string) $device->id)->count());
    }

    public function test_api_full_update_with_status_field_uses_the_same_transition_owner(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        $this->actingAs($admin)->putJson("/api/devices/{$device->id}", [
            'name' => $device->name,
            'serial_number' => $device->serial_number,
            'device_type_id' => $device->device_type_id,
            'lab_id' => $device->lab_id,
            'status' => false,
        ])->assertOk();

        $this->assertSame(false, (bool) $device->fresh()->status);
        $this->assertSame(1, DeviceStatusLog::query()->where('device_id', $device->id)->count());
        $this->assertSame(1, DomainEventOutbox::query()
            ->where('event_type', 'device.status.changed')
            ->where('aggregate_id', (string) $device->id)
            ->count());
    }

    public function test_blade_toggle_status_writes_one_log_and_one_outbox_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        $this->actingAs($admin)->post(route('devices.toggle-status', $device))->assertRedirect();

        $this->assertSame(false, (bool) $device->fresh()->status);
        $this->assertSame(1, DeviceStatusLog::query()->where('device_id', $device->id)->count());
        $this->assertSame(1, DomainEventOutbox::query()
            ->where('event_type', 'device.status.changed')
            ->where('aggregate_id', (string) $device->id)
            ->count());
    }

    public function test_api_and_blade_both_route_through_one_command_path_never_double(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        // API turns it off, Blade turns it back on — two distinct real transitions, each must emit
        // exactly one log + one event, never two for either entrypoint.
        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/status", ['status' => false])->assertOk();
        $this->actingAs($admin)->post(route('devices.toggle-status', $device))->assertRedirect();

        $this->assertSame(true, (bool) $device->fresh()->status);
        $this->assertSame(2, DeviceStatusLog::query()->where('device_id', $device->id)->count());
        $this->assertSame(2, DomainEventOutbox::query()
            ->where('event_type', 'device.status.changed')
            ->where('aggregate_id', (string) $device->id)
            ->count());
    }
}
