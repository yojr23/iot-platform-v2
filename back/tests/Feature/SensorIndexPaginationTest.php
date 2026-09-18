<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4: the sensor inventory list must paginate deterministically (stable id order so pages
 * never overlap/drop rows), search server-side across pages, and filter by status server-side.
 */
class SensorIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeSensors(int $count): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $type = SensorType::factory()->create();
        Sensor::factory()->count($count)->create([
            'device_id' => $device->id,
            'sensor_type_id' => $type->id,
            'status' => true,
        ]);
    }

    public function test_pages_are_deterministic_and_do_not_overlap(): void
    {
        $this->makeSensors(30);
        $admin = $this->admin();

        $page1 = $this->actingAs($admin)->getJson('/api/sensors?per_page=10&page=1')
            ->assertOk()->json('data');
        $page2 = $this->actingAs($admin)->getJson('/api/sensors?per_page=10&page=2')
            ->assertOk()->json('data');

        $ids1 = collect($page1)->pluck('id');
        $ids2 = collect($page2)->pluck('id');

        $this->assertCount(10, $ids1);
        $this->assertCount(10, $ids2);
        $this->assertEmpty($ids1->intersect($ids2), 'pages must not overlap');
        // Deterministic ascending id order.
        $this->assertSame($ids1->sort()->values()->all(), $ids1->values()->all());
        $this->assertTrue($ids2->min() > $ids1->max(), 'page 2 ids come after page 1');
    }

    public function test_search_finds_a_row_beyond_the_first_page(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $type = SensorType::factory()->create();
        Sensor::factory()->count(25)->create(['device_id' => $device->id, 'sensor_type_id' => $type->id, 'status' => true]);
        $needle = Sensor::factory()->create([
            'device_id' => $device->id,
            'sensor_type_id' => $type->id,
            'name' => 'Unicorn Marker Sensor',
            'status' => true,
        ]);

        $data = $this->actingAs($this->admin())
            ->getJson('/api/sensors?per_page=10&search=Unicorn Marker')
            ->assertOk()->json('data');

        $this->assertEqualsCanonicalizing([$needle->id], collect($data)->pluck('id')->all());
    }

    public function test_status_filter_is_applied_server_side(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $type = SensorType::factory()->create();
        Sensor::factory()->count(5)->create(['device_id' => $device->id, 'sensor_type_id' => $type->id, 'status' => true]);
        Sensor::factory()->count(3)->create(['device_id' => $device->id, 'sensor_type_id' => $type->id, 'status' => false]);

        $active = $this->actingAs($this->admin())->getJson('/api/sensors?status=active&per_page=100')
            ->assertOk()->json('data');
        $inactive = $this->actingAs($this->admin())->getJson('/api/sensors?status=inactive&per_page=100')
            ->assertOk()->json('data');

        $this->assertCount(5, $active);
        $this->assertCount(3, $inactive);
        $this->assertTrue(collect($active)->every(fn ($s) => $s['status'] === true));
        $this->assertTrue(collect($inactive)->every(fn ($s) => $s['status'] === false));
    }

    public function test_like_wildcards_in_search_are_treated_literally(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $type = SensorType::factory()->create();
        Sensor::factory()->create(['device_id' => $device->id, 'sensor_type_id' => $type->id, 'name' => 'Plain Sensor', 'status' => true]);
        $literal = Sensor::factory()->create(['device_id' => $device->id, 'sensor_type_id' => $type->id, 'name' => '100% Humidity Sensor', 'status' => true]);

        // "%" must match the literal percent, not act as a wildcard that returns everything.
        $data = $this->actingAs($this->admin())
            ->getJson('/api/sensors?per_page=100&search='.rawurlencode('100%'))
            ->assertOk()->json('data');

        $this->assertEqualsCanonicalizing([$literal->id], collect($data)->pluck('id')->all());
    }

    public function test_per_page_is_clamped_to_the_safe_maximum(): void
    {
        $this->makeSensors(5);

        $response = $this->actingAs($this->admin())->getJson('/api/sensors?per_page=99999')
            ->assertOk();

        $this->assertLessThanOrEqual(100, $response->json('per_page'));
    }
}
