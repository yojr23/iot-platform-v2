<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QueueSmokeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $filename,
        public string $payload
    ) {
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        Log::info('QueueSmokeJob: processing', [
            'filename' => $this->filename,
            'payload_bytes' => strlen($this->payload),
        ]);

        file_put_contents(storage_path('app/' . $this->filename), $this->payload);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('QueueSmokeJob: completed', [
            'filename' => $this->filename,
            'duration_ms' => $durationMs,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('QueueSmokeJob: failed', [
            'filename' => $this->filename,
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
