<?php

namespace App\Observers;

use App\Jobs\EvaluateSensorReadingAlerts;
use App\Models\SensorReading;
use Illuminate\Support\Facades\Log;

/**
 * `checkForAlert()` remains delegated to `App\Services\Alerts\AlertService`, the sole
 * rule-evaluation owner. This observer only hands the reading ID to the queue after commit, so both
 * the legacy API and async raw-ingestion paths use the same durable evaluation path.
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

        EvaluateSensorReadingAlerts::dispatch($sensorReading->id)->afterCommit();

        Log::debug('SensorReadingObserver: Alert evaluation queued after commit', [
            'sensor_reading_id' => $sensorReading->id,
        ]);
    }
}
