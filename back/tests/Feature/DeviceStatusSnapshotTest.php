<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceStatusSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_snapshot_is_authenticated_and_cursor_paged_with_event_watermarks(): void
    {
        $first = Device::factory()->create(['status' => true, 'is_active' => true]);
        $second = Device::factory()->create(['status' => false, 'is_active' => false]);
        $third = Device::factory()->create(['status' => true, 'is_active' => true]);

        $firstEvent = DomainEventOutbox::factory()->create([
            'event_type' => 'device.status.changed',
            'aggregate_type' => 'device',
            'aggregate_id' => (string) $first->id,
        ]);
        DomainEventOutbox::factory()->create([
            'event_type' => 'device.status.changed',
            'aggregate_type' => 'device',
            'aggregate_id' => (string) $second->id,
        ]);
        $latestSecondEvent = DomainEventOutbox::factory()->create([
            'event_type' => 'device.status.changed',
            'aggregate_type' => 'device',
            'aggregate_id' => (string) $second->id,
        ]);

        $this->getJson('/api/devices/status-snapshot')->assertUnauthorized();

        $user = User::factory()->create();
        $firstPage = $this->actingAs($user)->getJson('/api/devices/status-snapshot?per_page=2');

        $firstPage->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.device_id', $first->id)
            ->assertJsonPath('data.0.event_sequence', $firstEvent->id)
            ->assertJsonPath('data.1.device_id', $second->id)
            ->assertJsonPath('data.1.event_sequence', $latestSecondEvent->id)
            ->assertJsonPath('next_cursor', $second->id);

        $this->actingAs($user)->getJson("/api/devices/status-snapshot?per_page=2&cursor={$second->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_id', $third->id)
            ->assertJsonPath('data.0.event_sequence', 0)
            ->assertJsonPath('next_cursor', null);
    }
}
