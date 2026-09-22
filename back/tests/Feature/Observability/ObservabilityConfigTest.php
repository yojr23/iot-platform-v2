<?php

namespace Tests\Feature\Observability;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Tests\TestCase;

class ObservabilityConfigTest extends TestCase
{
    /**
     * AUTHORED — MAC EXECUTION PENDING.
     *
     * The configuration contract keeps structured output in storage/logs and
     * gives the three bounded channels their intentionally different retention.
     */
    public function test_structured_log_channels_are_json_rotating_files_with_bounded_retention(): void
    {
        $channels = config('logging.channels');

        $expected = [
            'json' => [
                'filename' => storage_path('logs/application.json'),
                'maxFiles' => 14,
                'level' => env('LOG_LEVEL', 'debug'),
            ],
            'audit' => [
                'filename' => storage_path('logs/audit.json'),
                'maxFiles' => 90,
                'level' => 'info',
            ],
            'health' => [
                'filename' => storage_path('logs/health.json'),
                'maxFiles' => 30,
                'level' => 'info',
            ],
        ];

        foreach ($expected as $name => $contract) {
            $this->assertSame('monolog', $channels[$name]['driver']);
            $this->assertSame(RotatingFileHandler::class, $channels[$name]['handler']);
            $this->assertSame($contract['filename'], $channels[$name]['handler_with']['filename']);
            $this->assertSame($contract['maxFiles'], $channels[$name]['handler_with']['maxFiles']);
            $this->assertSame($contract['level'], $channels[$name]['level']);
            $this->assertSame(JsonFormatter::class, $channels[$name]['formatter']);
        }

        foreach (['audit', 'health'] as $name) {
            $this->assertSame(0640, $channels[$name]['handler_with']['filePermission']);
        }

        $this->assertSame(60, config('observability.log_rate_limit.window_seconds'));
        $this->assertSame(20, config('observability.log_rate_limit.max_events'));
        $this->assertSame(['redis_stream', 'redis_cache', 'reverb'], config('observability.circuit_breakers.allowed'));
        $this->assertSame(5, config('observability.circuit_breakers.failure_threshold'));
        $this->assertSame(30, config('observability.circuit_breakers.open_seconds'));
        $this->assertSame(30, config('observability.circuit_breakers.half_open_after_seconds'));
        $this->assertSame(250, config('observability.slow_query_ms'));
        $this->assertSame(60, config('observability.health.queue_stale_after_seconds'));
    }
}
