<?php

namespace App\Observers;

use App\Models\SensorReading;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 4.3 (G0D row B2) scope note: unchanged this stage. `checkForAlert()` still
 * delegates to `App\Services\Alerts\AlertService` (the sole rule-evaluation owner) exactly as
 * before. The raw-ingestion consumer (`RawReadingNormalizer`, Stage 3) creates readings through the
 * same `Sensor::readings()->create()` call, so this observer fires for both the legacy
 * `SensorApiController::store()` path and the async raw pipeline — there is still only one
 * reading->alert evaluation path, not two.
 */
class SensorReadingObserver
{
    public function created(SensorReading $sensorReading)
    {
        Log::debug('SensorReadingObserver: Nueva lectura creada', [
            'sensor_reading_id' => $sensorReading->id,
            'sensor_id' => $sensorReading->sensor_id,
            'value' => $sensorReading->value,
        ]);

        $triggeredRules = $sensorReading->checkForAlert();

        if ($triggeredRules->isEmpty()) {
            Log::debug('SensorReadingObserver: No se activaron reglas de alerta para la lectura', [
                'sensor_reading_id' => $sensorReading->id,
            ]);
            return;
        }

        Log::info('SensorReadingObserver: Se activaron ' . $triggeredRules->count() . ' regla(s) de alerta', [
            'sensor_reading_id' => $sensorReading->id,
            'rules_count' => $triggeredRules->count(),
        ]);

        Log::debug('SensorReadingObserver: Finalizó procesamiento de reglas para la lectura', [
            'sensor_reading_id' => $sensorReading->id,
        ]);
    }
}
