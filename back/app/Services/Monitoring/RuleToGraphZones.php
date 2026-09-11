<?php

namespace App\Services\Monitoring;

use App\Models\AlertRule;
use App\Models\Sensor;
use App\Services\Alerts\AlertService;
use Illuminate\Support\Collection;

/**
 * docs/implementation/graph-semantic-zones-plan.md (GRAPH-001/004/005/006/007/008) — the single
 * server-owned normalizer that turns a sensor's applicable `AlertRule`s into ordered,
 * non-overlapping, precedence-resolved severity intervals for graph rendering.
 *
 * Existing code reused: `AlertService::applicableRules()` (extracted from this same class for this
 * task) for rule scoping — sensor_type_id + optional device_id + optional sensor_id, min/max
 * defined. The interval boundary test mirrors `AlertService::triggeredRulesForReading()`'s
 * inclusive `value <= min_value` / `value >= max_value` exactly (frozen GRAPH-004); this class
 * does not reimplement or reinterpret that comparison.
 * Existing owner retired/delegated: none — no prior graph-zone normalizer existed anywhere in the
 * codebase. `AlertService` remains the sole rule-evaluation/creation owner for alerting; this class
 * only re-projects the same rules for the graph and never creates/evaluates `Alert` rows.
 * Compatibility window: n/a.
 */
class RuleToGraphZones
{
    private const PRECEDENCE = ['danger' => 3, 'warning' => 2, 'info' => 1, 'normal' => 0];

    public function __construct(private AlertService $alertService)
    {
    }

    /**
     * @return array{
     *     zones: list<array{from: float|null, to: float|null, severity: string}>,
     *     boundaries: list<array{value: float, severity: string, bound: string, rule_id: int}>,
     * }
     */
    public function zonesFor(Sensor $sensor): array
    {
        $rules = $this->alertService->applicableRules($sensor)
            ->filter(fn (AlertRule $rule) => is_numeric($rule->min_value) || is_numeric($rule->max_value))
            ->values();

        if ($rules->isEmpty()) {
            return [
                'zones' => [['from' => null, 'to' => null, 'severity' => 'neutral']],
                'boundaries' => [],
            ];
        }

        $breakpoints = $rules
            ->flatMap(fn (AlertRule $rule) => array_filter([
                is_numeric($rule->min_value) ? (float) $rule->min_value : null,
                is_numeric($rule->max_value) ? (float) $rule->max_value : null,
            ], fn ($value) => $value !== null))
            ->unique()
            ->sort()
            ->values();

        $severityAt = fn (float $value): string => $this->severityAt($value, $rules);

        // Sweep the real line left to right. Between two consecutive breakpoints, coverage of
        // every rule is constant (a rule's min/max threshold is the only point where its coverage
        // can change), so a single representative point per open segment is exact — no epsilon
        // heuristics needed. Boundary points themselves are evaluated with the same inclusive
        // comparison AlertService uses, so a boundary always lands on the side AlertService would
        // actually trigger.
        $raw = [];
        $prev = null;

        foreach ($breakpoints as $point) {
            $midpoint = $prev === null ? $point - 1 : ($prev + $point) / 2;
            $raw[] = ['from' => $prev, 'to' => $point, 'severity' => $severityAt($midpoint)];
            $raw[] = ['from' => $point, 'to' => $point, 'severity' => $severityAt($point)];
            $prev = $point;
        }
        $raw[] = ['from' => $prev, 'to' => null, 'severity' => $severityAt($prev + 1)];

        return [
            'zones' => $this->mergeAdjacent($raw),
            'boundaries' => $this->boundaries($rules),
        ];
    }

    private function severityAt(float $value, Collection $rules): string
    {
        $best = 'normal';

        foreach ($rules as $rule) {
            $minDefined = is_numeric($rule->min_value);
            $maxDefined = is_numeric($rule->max_value);

            $violated = ($minDefined && $value <= (float) $rule->min_value)
                || ($maxDefined && $value >= (float) $rule->max_value);

            if ($violated && self::PRECEDENCE[$rule->severity] > self::PRECEDENCE[$best]) {
                $best = $rule->severity;
            }
        }

        return $best;
    }

    /**
     * @param list<array{from: float|null, to: float|null, severity: string}> $raw
     * @return list<array{from: float|null, to: float|null, severity: string}>
     */
    private function mergeAdjacent(array $raw): array
    {
        $merged = [];

        foreach ($raw as $segment) {
            $last = end($merged);
            if ($last !== false && $last['severity'] === $segment['severity']) {
                $merged[array_key_last($merged)]['to'] = $segment['to'];
                continue;
            }
            $merged[] = $segment;
        }

        return $merged;
    }

    /**
     * @return list<array{value: float, severity: string, bound: string, rule_id: int}>
     */
    private function boundaries(Collection $rules): array
    {
        $boundaries = [];

        foreach ($rules as $rule) {
            if (is_numeric($rule->min_value)) {
                $boundaries[] = ['value' => (float) $rule->min_value, 'severity' => $rule->severity, 'bound' => 'min', 'rule_id' => $rule->id];
            }
            if (is_numeric($rule->max_value)) {
                $boundaries[] = ['value' => (float) $rule->max_value, 'severity' => $rule->severity, 'bound' => 'max', 'rule_id' => $rule->id];
            }
        }

        usort($boundaries, fn ($a, $b) => $a['value'] <=> $b['value']);

        return $boundaries;
    }
}
