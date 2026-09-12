<?php

namespace Tests\Feature\Api;

use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-EXPORT-001 / SEC-INV-001: sensor export must be bounded (mandatory date range,
 * max window, hard row cap) and the sensor inventory must be paginated with a max page size.
 */
class SensorExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_export_requires_a_date_range(): void
    {
        $sensor = Sensor::factory()->create();

        $this->actingAs($this->admin())
            ->getJson("/api/sensors/{$sensor->id}/readings/export")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_export_rejects_a_window_longer_than_the_maximum(): void
    {
        $sensor = Sensor::factory()->create();

        // 60 days exceeds the 31-day cap.
        $this->actingAs($this->admin())
            ->getJson("/api/sensors/{$sensor->id}/readings/export?from=2026-01-01&to=2026-03-01")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_export_accepts_a_valid_bounded_window(): void
    {
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->count(3)->create([
            'sensor_id' => $sensor->id,
            'reading_time' => '2026-01-05 10:00:00',
        ]);

        $this->actingAs($this->admin())
            ->getJson("/api/sensors/{$sensor->id}/readings/export?from=2026-01-01&to=2026-01-31")
            ->assertOk()
            ->assertJsonStructure(['sensor', 'readings']);
    }

    public function test_sensor_inventory_is_paginated_with_a_max_page_size(): void
    {
        Sensor::factory()->count(5)->create();

        $response = $this->actingAs($this->admin())
            ->getJson('/api/sensors?per_page=1000')
            ->assertOk();

        // Paginated envelope present.
        $response->assertJsonStructure(['data', 'meta' => ['per_page', 'current_page']]);
        // per_page is clamped to the hard maximum (100), never the requested 1000.
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }
}
