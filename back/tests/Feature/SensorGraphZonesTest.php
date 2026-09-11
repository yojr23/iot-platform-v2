<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/implementation/graph-semantic-zones-plan.md (GRAPH-001/008) — the authenticated projection
 * of `RuleToGraphZones`, same normalizer the public bootstrap consumes. Fuller metadata (rule ids,
 * boundary values) is fine here since the caller is authenticated; notification policy never
 * appears regardless of audience.
 */
class SensorGraphZonesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $sensor = Sensor::factory()->create();

        $this->getJson("/api/sensors/{$sensor->id}/graph-zones")->assertUnauthorized();
    }

    public function test_authenticated_user_gets_zones_and_boundaries_with_rule_ids(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();
        $rule = AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 30,
            'severity' => 'danger',
            'message' => 'Hot',
            'name' => 'Danger',
        ]);

        $response = $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}/graph-zones");

        $response->assertOk()
            ->assertJsonPath('zones', [
                ['from' => null, 'to' => 30.0, 'severity' => 'normal'],
                ['from' => 30.0, 'to' => null, 'severity' => 'danger'],
            ])
            ->assertJsonPath('boundaries.0.rule_id', $rule->id)
            ->assertJsonPath('boundaries.0.severity', 'danger');
    }

    public function test_sensor_without_rules_returns_neutral_never_normal(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}/graph-zones")
            ->assertOk()
            ->assertJsonPath('zones', [['from' => null, 'to' => null, 'severity' => 'neutral']]);
    }

    /** Notification policy (email delivery, rate limits) never leaks into the graph projection. */
    public function test_response_never_carries_notification_policy_fields(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 30,
            'severity' => 'danger',
            'message' => 'Hot',
            'name' => 'Danger',
        ]);

        $response = $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}/graph-zones");

        $body = json_encode($response->json());
        $this->assertStringNotContainsStringIgnoringCase('email', $body);
        $this->assertStringNotContainsStringIgnoringCase('notif', $body);
    }
}
