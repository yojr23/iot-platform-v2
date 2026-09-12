<?php

namespace Tests\Feature\Api;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-BOLA-001: private sensor reads are centralized behind SensorPolicy::view(), which
 * delegates to ResourceAccessService::canViewSensor(). No lab/ownership model exists yet, so the
 * documented rule is "any verified authenticated user may read"; this test locks in that guests
 * are rejected, unverified users are forbidden, and verified users succeed — for every action
 * that now calls $this->authorize('view', $sensor).
 */
class SensorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_sensor_show(): void
    {
        $sensor = Sensor::factory()->create();

        $this->getJson("/api/sensors/{$sensor->id}")->assertUnauthorized();
    }

    public function test_unverified_user_cannot_view_sensor_show(): void
    {
        $user = User::factory()->unverified()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}")
            ->assertForbidden();
    }

    public function test_verified_user_can_view_sensor_show(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}")
            ->assertOk();
    }

    public function test_unverified_user_cannot_view_sensor_readings(): void
    {
        $user = User::factory()->unverified()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/readings")
            ->assertForbidden();
    }

    public function test_verified_user_can_view_sensor_readings(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/readings")
            ->assertOk();
    }

    public function test_unverified_user_cannot_view_sensor_series(): void
    {
        $user = User::factory()->unverified()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/series?from=2026-05-02T00:00:00Z&to=2026-05-03T00:00:00Z")
            ->assertForbidden();
    }

    public function test_verified_user_can_view_sensor_series(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/series?from=2026-05-02T00:00:00Z&to=2026-05-03T00:00:00Z")
            ->assertOk();
    }

    public function test_unverified_user_cannot_view_sensor_latest_readings(): void
    {
        $user = User::factory()->unverified()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/latest-readings")
            ->assertForbidden();
    }

    public function test_verified_user_can_view_sensor_latest_readings(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/latest-readings")
            ->assertOk();
    }

    public function test_unverified_user_cannot_export_sensor_readings(): void
    {
        $user = User::factory()->unverified()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/readings/export")
            ->assertForbidden();
    }

    public function test_verified_user_can_export_sensor_readings(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/readings/export")
            ->assertOk();
    }

    public function test_unverified_user_cannot_view_sensor_graph_zones(): void
    {
        $user = User::factory()->unverified()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/graph-zones")
            ->assertForbidden();
    }

    public function test_verified_user_can_view_sensor_graph_zones(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/sensors/{$sensor->id}/graph-zones")
            ->assertOk();
    }
}
