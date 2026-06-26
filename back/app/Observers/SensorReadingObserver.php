<?php

namespace App\Observers;

use App\Models\SensorReading;
use Illuminate\Support\Facades\Log;

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
