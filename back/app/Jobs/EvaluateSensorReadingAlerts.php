<?php

namespace App\Jobs;

use App\Models\SensorReading;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class EvaluateSensorReadingAlerts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $sensorReadingId)
    {
    }

    public function backoff(): array
    {
        return [1, 5, 15, 60];
    }

    public function handle(): void
    {
        $reading = SensorReading::query()->find($this->sensorReadingId);

        if ($reading === null) {
            return;
        }

        $reading->checkForAlert();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('EvaluateSensorReadingAlerts: permanently failed after all retries', [
            'sensor_reading_id' => $this->sensorReadingId,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
