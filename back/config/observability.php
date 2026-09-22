<?php

$boundedInteger = static function (string $name, int $default, int $minimum, int $maximum): int {
    return max($minimum, min((int) env($name, $default), $maximum));
};

$knownBreakers = ['redis_stream', 'redis_cache', 'reverb'];
$configuredBreakers = array_filter(explode(',', (string) env('OBS_ALLOWED_BREAKERS', implode(',', $knownBreakers))));

return [
    // Configuration only: this task deliberately adds no breaker implementation.
    'circuit_breakers' => [
        'allowed' => array_values(array_intersect($knownBreakers, $configuredBreakers)),
        'failure_threshold' => $boundedInteger('OBS_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5, 1, 1000),
        'open_seconds' => $boundedInteger('OBS_CIRCUIT_BREAKER_OPEN_SECONDS', 30, 1, 86400),
        'half_open_after_seconds' => $boundedInteger('OBS_CIRCUIT_BREAKER_HALF_OPEN_AFTER_SECONDS', 30, 1, 86400),
    ],

    'log_rate_limit' => [
        'window_seconds' => $boundedInteger('OBS_LOG_RATE_WINDOW_SECONDS', 60, 1, 3600),
        'max_events' => $boundedInteger('OBS_LOG_RATE_MAX_EVENTS', 20, 1, 10000),
    ],

    // Configuration only: slow-query instrumentation is intentionally out of scope.
    'slow_query_ms' => $boundedInteger('OBS_SLOW_QUERY_MS', 250, 1, 60000),

    // Configuration only: health probes and schedulers are intentionally out of scope.
    'health' => [
        'queue_stale_after_seconds' => $boundedInteger('OBS_QUEUE_STALE_AFTER_SECONDS', 60, 1, 86400),
    ],
];
