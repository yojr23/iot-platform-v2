<?php
/**
 * Phase D concurrency worker. Boots Laravel, waits for a shared wall-clock barrier, then calls the
 * REAL SensorMappingService::mapSensor() once for a fixed identity. Two of these run in parallel
 * against real MySQL to force the first-ever (empty-set) and replacement mapping races.
 *
 * argv: [1]=barrier_epoch_micros [2]=device_id [3]=sensor_id [4]=external_key
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$barrier = (float) $argv[1];
$deviceId = (int) $argv[2];
$sensorId = (int) $argv[3];
$externalKey = $argv[4];

$device = App\Models\Device::findOrFail($deviceId);
$sensor = App\Models\Sensor::findOrFail($sensorId);

// Busy-wait to the barrier so both workers hit the critical section together.
while (microtime(true) < $barrier) {
    usleep(200);
}

try {
    app(App\Services\SensorMappingService::class)->mapSensor(
        $device, $sensor, 'ingestion_service', $externalKey
    );
    fwrite(STDOUT, "OK\n");
} catch (Throwable $e) {
    // A serialization failure / unique violation here is a CORRECT outcome (one writer wins).
    fwrite(STDOUT, 'REJECTED: '.substr($e->getMessage(), 0, 80)."\n");
}
