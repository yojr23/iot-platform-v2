<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\Ingestion\DomainEventRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Reglas candidatas para un sensor: mismo sensor_type_id, device_id/sensor_id nulos o
     * coincidentes, y min o max definido. Este es el ÚNICO resolutor de scoping de reglas —
     * `triggeredRulesForReading` (valor de una lectura) y `RuleToGraphZones` (bandas de gráfico)
     * reutilizan este método en lugar de duplicar el query.
     */
    public function applicableRules(Sensor $sensor): Collection
    {
        return AlertRule::query()
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
    }

    /**
     * Devuelve las reglas que se disparan con una lectura.
     */
    public function triggeredRulesForReading(SensorReading $reading): Collection
    {
        Log::info('AlertService:triggeredRulesForReading entry', ['reading_id' => $reading->id, 'sensor_id' => $reading->sensor_id]);

        $startTime = microtime(true);
        // Reuse the sensor already attached by SensorReadingService (avoids re-selecting `sensors`
        // per reading); fall back to a query for callers that didn't preload it (legacy path).
        $sensor = $reading->relationLoaded('sensor') && $reading->sensor !== null
            ? $reading->sensor->loadMissing('device.lab')
            : $reading->sensor()->with(['device.lab'])->first();

        if (! $sensor) {
            Log::info('AlertService:triggeredRulesForReading no sensor found', ['reading_id' => $reading->id]);
            return collect();
        }

        $alertRules = $this->applicableRules($sensor);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $result = $alertRules
            ->filter(function ($alertRule) use ($reading) {
                $minDefined = is_numeric($alertRule->min_value);
                $maxDefined = is_numeric($alertRule->max_value);

                $belowMin = $minDefined && $reading->value <= $alertRule->min_value;
                $aboveMax = $maxDefined && $reading->value >= $alertRule->max_value;

                return $belowMin || $aboveMax;
            })
            ->values();

        Log::info('AlertService:triggeredRulesForReading completed', [
            'reading_id' => $reading->id,
            'triggered_count' => $result->count(),
            'candidate_rules' => $alertRules->count(),
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('AlertService:triggeredRulesForReading slow query', ['duration_ms' => $durationMs, 'table' => 'alert_rules']);
        }

        return $result;
    }

    /**
     * Crea alertas por lectura (si no existen duplicadas por regla) y devuelve reglas disparadas.
     */
    public function createAlertsForReading(SensorReading $reading): Collection
    {
        Log::info('AlertService:createAlertsForReading entry', ['reading_id' => $reading->id]);

        $startTime = microtime(true);
        $triggeredRules = $this->triggeredRulesForReading($reading);
        $sensor = $reading->relationLoaded('sensor') && $reading->sensor !== null
            ? $reading->sensor->loadMissing(['sensorType', 'device.lab'])
            : $reading->sensor()->with(['sensorType', 'device.lab'])->first();
        $alertsCreated = 0;

        foreach ($triggeredRules as $alertRule) {
            DB::transaction(function () use ($reading, $alertRule, $sensor, &$alertsCreated): void {
                // Check inside the transaction to prevent race condition with concurrent readings.
                // The unique constraint on (sensor_reading_id, alert_rule_id) is the DB backstop.
                $alreadyExists = Alert::where('sensor_reading_id', $reading->id)
                    ->where('alert_rule_id', $alertRule->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyExists) {
                    return;
                }

                $alert = Alert::create([
                    'sensor_reading_id' => $reading->id,
                    'alert_rule_id' => $alertRule->id,
                    'resolved' => false,
                ]);

                $this->recorder->record('alert.triggered', 'alert', $alert->id, [
                    'alert_id' => $alert->id,
                    'message' => (string) ($alertRule->message ?? 'Alerta generada'),
                    'severity' => (string) ($alertRule->severity ?? 'warning'),
                    'value' => $reading->value !== null ? (float) $reading->value : null,
                    'sensor_name' => (string) ($sensor?->name ?? 'Sensor desconocido'),
                    'sensor_type' => (string) ($sensor?->sensorType?->name ?? ''),
                    'unit' => (string) ($sensor?->sensorType?->unit ?? ''),
                    'device_name' => (string) ($sensor?->device?->name ?? 'Dispositivo desconocido'),
                    'lab_name' => (string) ($sensor?->device?->lab?->name ?? 'Lab no definido'),
                    'timestamp' => $alert->created_at?->toIso8601String(),
                ]);

                $alertsCreated++;
            });
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('AlertService:createAlertsForReading completed', [
            'reading_id' => $reading->id,
            'triggered_rules' => $triggeredRules->count(),
            'alerts_created' => $alertsCreated,
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('AlertService:createAlertsForReading slow execution', ['duration_ms' => $durationMs]);
        }

        return $triggeredRules;
    }

    public function getActiveAlertsCount(): int
    {
        Log::info('AlertService:getActiveAlertsCount entry', ['scope' => 'active']);

        $startTime = microtime(true);
        $count = Alert::active()->count();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertService:getActiveAlertsCount completed', ['count' => $count, 'duration_ms' => $durationMs]);

        if ($durationMs > 100) {
            Log::warning('AlertService:getActiveAlertsCount slow query', ['duration_ms' => $durationMs, 'table' => 'alerts']);
        }

        return $count;
    }

    public function getActiveAlertsList(int $limit = 10): Collection
    {
        Log::info('AlertService:getActiveAlertsList entry', ['limit' => $limit]);

        $startTime = microtime(true);
        $result = Alert::withContext()
            ->active()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertService:getActiveAlertsList completed', [
            'count' => $result->count(),
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('AlertService:getActiveAlertsList slow query', ['duration_ms' => $durationMs, 'table' => 'alerts']);
        }

        return $result;
    }
}
