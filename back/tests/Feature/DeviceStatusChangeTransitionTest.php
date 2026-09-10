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

    public function test_manual_status_transition_does_not_falsify_last_device_communication(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $lastCommunication = now()->subHour()->startOfSecond();
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
            'last_communication' => $lastCommunication,
        ]);

        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/status", ['status' => false])
            ->assertOk();

        $this->assertTrue($device->fresh()->last_communication->equalTo($lastCommunication));
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

    /**
     * Gate 8: the outbox payload itself must carry the full immutable fact (status/is_active/
     * changed_at), not just `device_id` — `DomainEventBroadcastConsumer` no longer reloads the
     * `Device` row to learn what a given transition actually was.
     */
    public function test_outbox_payload_captures_the_full_immutable_fact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/status", ['status' => false])->assertOk();

        $outbox = DomainEventOutbox::query()
            ->where('event_type', 'device.status.changed')
            ->where('aggregate_id', (string) $device->id)
            ->firstOrFail();

        $this->assertSame($device->id, $outbox->payload['device_id']);
        $this->assertFalse($outbox->payload['status']);
        $this->assertFalse($outbox->payload['is_active']);
        $this->assertNotEmpty($outbox->payload['changed_at']);
    }

    /**
     * Gate 8 core regression: OFF then ON, both applied before any consumer runs, must leave two
     * outbox rows each frozen with the value true *at the time of that transition* — never both
     * reading back the row's final (current) value.
     */
    public function test_rapid_off_then_on_each_outbox_fact_keeps_its_own_value(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/status", ['status' => false])->assertOk();
        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/status", ['status' => true])->assertOk();

        $facts = DomainEventOutbox::query()
            ->where('event_type', 'device.status.changed')
            ->where('aggregate_id', (string) $device->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $facts);
        $this->assertFalse($facts[0]->payload['status']);
        $this->assertTrue($facts[1]->payload['status']);
        $this->assertTrue($facts[1]->id > $facts[0]->id);
    }
}
