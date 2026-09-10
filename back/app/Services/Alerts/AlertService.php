<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\SensorReading;
use App\Services\Ingestion\DomainEventRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PLAN.md Stage 7.5, G0D row B1 — AlertService remains the sole rule-evaluation/creation owner
 * (unchanged this stage); it now also owns writing the `alert.triggered` domain-outbox row in the
 * same transaction as `Alert::create()`, mirroring `AlertLifecycleService::resolveWithinTransaction()`
 * for `alert.resolved` (Stage 4.2).
 *
 * Existing code reused: `DomainEventRecorder` (Stage 4.1) — no second outbox writer.
 * Existing owner retired/delegated: `AlertObserver::created()` no longer broadcasts
 * `NewAlertTriggered` synchronously; `DomainEventBroadcastConsumer` is the new sole dispatcher
 * for that event, reached via this outbox row.
 * Compatibility window: none — `alert.triggered` and `alert.resolved` now share one delivery path.
 */
class AlertService
{
    public function __construct(private DomainEventRecorder $recorder)
    {
    }

    /**
     * Devuelve las reglas que se disparan con una lectura.
     */
    public function triggeredRulesForReading(SensorReading $reading): Collection
    {
        $sensor = $reading->sensor()->with(['device.lab'])->first();

        if (! $sensor) {
            return collect();
        }

        $alertRules = AlertRule::query()
            ->where('sensor_type_id', $sensor->sensor_type_id)
            ->where(function ($query): void {
                $query->whereNotNull('min_value')
                    ->orWhereNotNull('max_value');
            })
            ->where(function ($query) use ($sensor): void {
                $query->whereNull('device_id')
                    ->orWhere('device_id', $sensor->device_id);
            })
            ->where(function ($query) use ($sensor): void {
                $query->whereNull('sensor_id')
                    ->orWhere('sensor_id', $sensor->id);
            })
            ->get();

        return $alertRules
            ->filter(function ($alertRule) use ($reading) {
                $minDefined = is_numeric($alertRule->min_value);
                $maxDefined = is_numeric($alertRule->max_value);

                $belowMin = $minDefined && $reading->value <= $alertRule->min_value;
                $aboveMax = $maxDefined && $reading->value >= $alertRule->max_value;

                return $belowMin || $aboveMax;
            })
            ->values();
    }

    /**
     * Crea alertas por lectura (si no existen duplicadas por regla) y devuelve reglas disparadas.
     */
    public function createAlertsForReading(SensorReading $reading): Collection
    {
        $triggeredRules = $this->triggeredRulesForReading($reading);

        foreach ($triggeredRules as $alertRule) {
            $alreadyExists = Alert::where('sensor_reading_id', $reading->id)
                ->where('alert_rule_id', $alertRule->id)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            DB::transaction(function () use ($reading, $alertRule): void {
                $alert = Alert::create([
                    'sensor_reading_id' => $reading->id,
                    'alert_rule_id' => $alertRule->id,
                    'resolved' => false,
                ]);

                $this->recorder->record('alert.triggered', 'alert', $alert->id, [
                    'alert_id' => $alert->id,
                ]);
            });
        }

        return $triggeredRules;
    }

    public function getActiveAlertsCount(): int
    {
        return Alert::active()->count();
    }

    public function getActiveAlertsList(int $limit = 10): Collection
    {
        return Alert::withContext()
            ->active()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}

