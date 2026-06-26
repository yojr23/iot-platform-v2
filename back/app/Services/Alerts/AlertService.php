<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\SensorReading;
use Illuminate\Support\Collection;

class AlertService
{
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

            Alert::create([
                'sensor_reading_id' => $reading->id,
                'alert_rule_id' => $alertRule->id,
                'resolved' => false,
            ]);
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

