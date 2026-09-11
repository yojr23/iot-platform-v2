<?php

namespace Tests\Unit;

use App\Models\AlertRule;
use App\Models\Sensor;
use App\Services\Monitoring\RuleToGraphZones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/implementation/graph-semantic-zones-plan.md — TEST-001/002/003/005 for the single
 * server-owned normalizer. Boundary semantics mirror AlertService's inclusive
 * `value <= min_value` / `value >= max_value` exactly (GRAPH-004, frozen).
 */
class RuleToGraphZonesTest extends TestCase
{
    use RefreshDatabase;

    private function zonesFor(Sensor $sensor): array
    {
        return app(RuleToGraphZones::class)->zonesFor($sensor);
    }

    private function severityAt(array $zones, float $value): string
    {
        foreach ($zones as $zone) {
            $aboveFrom = $zone['from'] === null || $value >= $zone['from'];
            $belowTo = $zone['to'] === null || $value < $zone['to'];
            if ($aboveFrom && $belowTo) {
                return $zone['severity'];
            }
        }

        $this->fail("No zone covers value {$value}: " . json_encode($zones));
    }

    /**
     * TEST-001: values just below/at/above min and max map to the correct zone under the
     * inclusive rule. The exact-at-boundary case uses the `boundaries` entry (unambiguous, one
     * rule) rather than the reduced `{from,to}` zone list — a min-type boundary is owned by the
     * zone to its left (`value <= min`) while a max-type boundary is owned by the zone to its
     * right (`value >= max`); the flat `{from,to,severity}` pair alone can't encode which side
     * owns a shared endpoint between two DIFFERENT rules, which is exactly why `boundaries`
     * exists as the authoritative per-threshold record for labeling.
     */
    public function test_min_only_rule_is_inclusive_at_the_boundary(): void
    {
        $sensor = Sensor::factory()->create();
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => 10,
            'max_value' => null,
            'severity' => 'danger',
            'message' => 'Too low',
            'name' => 'MinOnly',
        ]);

        $result = $this->zonesFor($sensor);

        $this->assertSame('danger', $this->severityAt($result['zones'], 9.999));
        $this->assertSame('danger', $result['boundaries'][0]['severity']);
        $this->assertSame(10.0, $result['boundaries'][0]['value']);
        $this->assertSame('normal', $this->severityAt($result['zones'], 10.001));
    }

    public function test_max_only_rule_is_inclusive_at_the_boundary(): void
    {
        $sensor = Sensor::factory()->create();
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 20,
            'severity' => 'warning',
            'message' => 'Too high',
            'name' => 'MaxOnly',
        ]);

        $zones = $this->zonesFor($sensor)['zones'];

        $this->assertSame('normal', $this->severityAt($zones, 19.999));
        $this->assertSame('warning', $this->severityAt($zones, 20.0));
        $this->assertSame('warning', $this->severityAt($zones, 20.001));
    }

    public function test_min_and_max_rule_carves_a_normal_middle_band(): void
    {
        $sensor = Sensor::factory()->create();
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => 10,
            'max_value' => 20,
            'severity' => 'info',
            'message' => 'Outside safe range',
            'name' => 'MinMax',
        ]);

        $result = $this->zonesFor($sensor);

        $minBoundary = collect($result['boundaries'])->firstWhere('bound', 'min');
        $maxBoundary = collect($result['boundaries'])->firstWhere('bound', 'max');
        $this->assertSame('info', $minBoundary['severity']);
        $this->assertSame('info', $maxBoundary['severity']);
        $this->assertSame('normal', $this->severityAt($result['zones'], 15.0));
        $this->assertSame('info', $this->severityAt($result['zones'], 5.0));
        $this->assertSame('info', $this->severityAt($result['zones'], 25.0));
    }

    /** TEST-002: overlapping warning + danger rules resolve to danger > warning precedence, fragmented in order. */
    public function test_overlapping_warning_and_danger_rules_resolve_by_precedence(): void
    {
        $sensor = Sensor::factory()->create();
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 28,
            'severity' => 'warning',
            'message' => 'Warm',
            'name' => 'Warning',
        ]);
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

        $zones = $this->zonesFor($sensor)['zones'];

        $this->assertSame(
            [
                ['from' => null, 'to' => 28.0, 'severity' => 'normal'],
                ['from' => 28.0, 'to' => 30.0, 'severity' => 'warning'],
                ['from' => 30.0, 'to' => null, 'severity' => 'danger'],
            ],
            $zones
        );
    }

    public function test_three_way_overlap_still_orders_danger_warning_info_normal(): void
    {
        $sensor = Sensor::factory()->create();
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id, 'min_value' => null, 'max_value' => 10,
            'severity' => 'info', 'message' => 'i', 'name' => 'I',
        ]);
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id, 'min_value' => null, 'max_value' => 20,
            'severity' => 'warning', 'message' => 'w', 'name' => 'W',
        ]);
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id, 'min_value' => null, 'max_value' => 30,
            'severity' => 'danger', 'message' => 'd', 'name' => 'D',
        ]);

        $zones = $this->zonesFor($sensor)['zones'];

        $this->assertSame('normal', $this->severityAt($zones, 5));
        $this->assertSame('info', $this->severityAt($zones, 10));
        $this->assertSame('warning', $this->severityAt($zones, 20));
        $this->assertSame('danger', $this->severityAt($zones, 30));
        $this->assertSame('danger', $this->severityAt($zones, 999));
    }

    /** TEST-003: editing a rule changes the next normalizer output — no cached code path. */
    public function test_editing_a_rule_changes_the_next_normalizer_output(): void
    {
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

        $this->assertSame('danger', $this->severityAt($this->zonesFor($sensor)['zones'], 30));

        $rule->update(['max_value' => 50]);

        $zones = $this->zonesFor($sensor)['zones'];
        $this->assertSame('normal', $this->severityAt($zones, 30));
        $this->assertSame('danger', $this->severityAt($zones, 50));
    }

    /** TEST-005: no applicable rules → neutral, never green "normal". */
    public function test_sensor_with_no_applicable_rules_is_neutral_not_normal(): void
    {
        $sensor = Sensor::factory()->create();

        $result = $this->zonesFor($sensor);

        $this->assertSame(
            [['from' => null, 'to' => null, 'severity' => 'neutral']],
            $result['zones']
        );
        $this->assertSame([], $result['boundaries']);
    }

    public function test_rule_without_min_or_max_defined_is_ignored_and_stays_neutral(): void
    {
        $sensor = Sensor::factory()->create();
        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => null,
            'severity' => 'danger',
            'message' => 'No thresholds set',
            'name' => 'Empty',
        ]);

        $result = $this->zonesFor($sensor);

        $this->assertSame('neutral', $result['zones'][0]['severity']);
    }

    public function test_device_and_sensor_scoped_rules_are_reused_from_alert_service(): void
    {
        $sensor = Sensor::factory()->create();
        $otherSensor = Sensor::factory()->create(['sensor_type_id' => $sensor->sensor_type_id]);

        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => null,
            'sensor_id' => $otherSensor->id,
            'min_value' => null,
            'max_value' => 5,
            'severity' => 'danger',
            'message' => 'Scoped to other sensor only',
            'name' => 'OtherSensorOnly',
        ]);

        $result = $this->zonesFor($sensor);

        $this->assertSame([['from' => null, 'to' => null, 'severity' => 'neutral']], $result['zones']);
    }

    public function test_boundaries_carry_rule_severity_and_id_for_labeling(): void
    {
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

        $result = $this->zonesFor($sensor);

        $this->assertSame(
            [['value' => 30.0, 'severity' => 'danger', 'bound' => 'max', 'rule_id' => $rule->id]],
            $result['boundaries']
        );
    }
}
