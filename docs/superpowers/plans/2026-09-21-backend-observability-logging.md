# Backend Observability and Resilient Logging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make backend operational logs correlated, structured, bounded, actionable, and safe while exposing health, retry, throttling, audit, and circuit-breaker state without changing product API contracts or persistence behavior.

**Architecture:** Add a small, shared observability layer rather than adding hand-maintained context arrays to every controller. HTTP middleware establishes a validated correlation ID and response header; worker entry points establish an event/consumer context; `OperationalLogger` provides rate-limited operational events; `CircuitBreaker` guards only retry-safe remote calls. Existing `AuditService`, `EventPipelineMetricsService`, queue jobs, and Redis-stream consumers remain the domain owners.

**Tech Stack:** Laravel 12, PHP 8.2, Monolog, Laravel Cache/RateLimiter, Redis Streams, MySQL 8, database queue, Reverb, PHPUnit 11, Docker Compose.

**Spec:** User-provided “Backend Logging Audit — Summary & Improvement Plan” in the 2026-09-21 conversation; repository evidence in `back/bootstrap/app.php`, `back/config/logging.php`, and `back/app/Services/Monitoring/EventPipelineMetricsService.php`.

## Global Constraints

- Do not add database tables, alter domain data, change endpoint payloads/statuses, or add a third-party observability service.
- Keep `audit_logs` as the durable audit record; the new audit log channel is a structured secondary sink, not a replacement.
- Never log credentials, API keys, bearer tokens, cookies, full request bodies, raw SQL bindings, full SQL statements, or clear-text setting values. Preserve and extend `back/tests/Feature/Api/LoggingRedactionTest.php`.
- Accept an incoming `X-Request-Id` only when it is a canonical UUID; otherwise generate a UUIDv7. Always return the effective value in `X-Request-Id`.
- Production must use JSON logs (`LOG_STACK=json`); local development may retain the human-readable `single` channel.
- Log rate limiting must use fixed low-cardinality categories and emit a suppression summary. Audit records, first circuit transitions, and final DLQ records must never be suppressed.
- Circuit breakers apply only to retry-safe Redis publisher, cache projection, and Reverb broadcast calls. A breaker-open result must leave the existing source message pending or retain the current best-effort cache behavior; it must never ACK, delete, or mark an outbox delivered.
- Do not claim PHP/Laravel has a connection pool. Report MySQL server connection counters (`Threads_connected`, `Threads_running`, `Max_used_connections`, `max_connections`) only when the driver is MySQL; otherwise report that measurement as unavailable.
- Slow-query logging records a normalized query fingerprint, operation/table classification, binding *types*, and duration. It must not call `toRawSql()`, log bindings, run `EXPLAIN` on a user request, or claim `rows_examined` is available.

## Review Focus

- A malicious or oversized request-ID header: it is replaced with a generated UUID and never reflected unsafely.
- A Redis/Reverb outage: logs record one open transition and bounded summaries, while stream messages remain pending for reclaim rather than being lost.
- Repeated 429 responses: one structured event contains route scope and standard limit headers without leaking a RateLimiter cache key.
- A sensitive configuration or key rotation: both durable audit row and JSON audit event contain actor/resource metadata but no secret or old/new value.
- A MySQL/Redis outage during health checks: the command reports a failed dependency and exits non-zero without masking the original condition or generating an exception storm.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `back/app/Http/Middleware/AssignRequestContext.php` | Validate/generate HTTP correlation ID, attach request/log context, and add response header. |
| `back/app/Http/Middleware/LogRateLimitedResponse.php` | Log rate-limit responses once per bounded category after the throttle middleware returns a 429. |
| `back/app/Support/ObservabilityContext.php` | Scoped context helpers for HTTP, CLI worker, and individual stream-event logging without context leakage in daemons. |
| `back/app/Support/OperationalLogger.php` | Fixed-cardinality, rate-limited operational event logging plus suppression summaries. |
| `back/app/Support/CircuitBreaker.php` and `back/app/Exceptions/CircuitOpenException.php` | Cache-backed state machine for retry-safe remote dependencies. |
| `back/app/Support/SafeExceptionContext.php` and `back/app/Support/SlowQueryContext.php` | Redacted exception and SQL-fingerprint contexts shared by reporters/listeners. |
| `back/app/Console/Commands/RunObservabilityHealthCheck.php` | Explicit DB/Redis/queue/Reverb/stream health probe and structured `health` event. |
| `back/app/Services/Monitoring/OperationalHealthService.php` | Dependency probes, queue counts/age, MySQL counters, pipeline snapshot, and normalized health result. |
| `back/config/logging.php`, `back/config/observability.php`, `back/.env.example` | JSON/audit/health channels and explicit thresholds/retention/configuration. |
| `back/bootstrap/app.php`, `back/app/Providers/AppServiceProvider.php`, `back/routes/console.php`, `docker-compose.yml` | Register middleware/listeners, log 429s, schedule health checks, and run the scheduler container. |
| Existing publishers/consumers/projection, `AuditService`, `TrackApiPerformance`, and queue jobs | Emit structured lifecycle, retry, DLQ, cache, audit, and circuit state using the shared layer. |
| `back/tests/Feature/Observability/*` and existing security/consumer tests | Pin the public behavior, redaction, non-loss, and configuration contracts. |

### Task 1: Establish configuration, JSON channels, and the redaction contract

**Files:**
- Create: `back/config/observability.php`
- Modify: `back/config/logging.php:1-143`
- Modify: `back/.env.example`
- Modify: `back/tests/Feature/Api/LoggingRedactionTest.php`
- Create: `back/tests/Feature/Observability/LoggingConfigurationTest.php`

**Interfaces:**
- Produces `config('observability.*')`: exact thresholds, rate windows, and enabled dependency breaker names.
- Produces channels `json`, `audit`, and `health`, all formatted as JSON objects with the same field names Laravel supplies (`message`, `context`, `level`, `datetime`).
- Consumes the existing `LOG_CHANNEL`, `LOG_STACK`, and `LOG_LEVEL` environment contract; production sets `LOG_STACK=json`, not a hard-coded channel in code.

- [ ] **Step 1: Write failing configuration and redaction tests**

  Create `back/tests/Feature/Observability/LoggingConfigurationTest.php` with assertions for the production-safe configuration shape:

  ```php
  public function test_json_audit_and_health_channels_are_configured_with_json_formatters(): void
  {
      foreach (['json', 'audit', 'health'] as $channel) {
          $config = config("logging.channels.{$channel}");

          $this->assertSame('monolog', $config['driver']);
          $this->assertSame(\Monolog\Formatter\JsonFormatter::class, $config['formatter']);
      }

      $this->assertSame(90, config('logging.channels.audit.handler_with.maxFiles'));
  }

  public function test_observability_defaults_are_bounded_and_use_fixed_dependency_names(): void
  {
      $this->assertSame(60, config('observability.log_rate_limit.window_seconds'));
      $this->assertSame(20, config('observability.log_rate_limit.max_events'));
      $this->assertSame(['redis_stream', 'redis_cache', 'reverb'], config('observability.circuit_breakers.allowed'));
  }
  ```

  Extend `LoggingRedactionTest` with an audit-specific assertion: invoke `AuditService::logSystemSettingChanged('mail_password', 'old-secret', 'new-secret', $request)` and assert captured audit context contains `setting_key` and `value_changed: true`, but neither secret string.

- [ ] **Step 2: Run the focused tests and verify expected failures**

  Run:

  ```powershell
  Set-Location back
  php artisan test --filter="LoggingConfigurationTest|LoggingRedactionTest"
  ```

  Expected: FAIL because `observability.php`, the three channels, and safe audit projection do not exist.

- [ ] **Step 3: Add explicit configuration and channels**

  Add `back/config/observability.php` with fixed categories and environment-overridable values:

  ```php
  <?php

  return [
      'log_rate_limit' => [
          'window_seconds' => (int) env('OBS_LOG_RATE_WINDOW_SECONDS', 60),
          'max_events' => (int) env('OBS_LOG_RATE_MAX_EVENTS', 20),
      ],
      'circuit_breakers' => [
          'allowed' => ['redis_stream', 'redis_cache', 'reverb'],
          'failure_threshold' => (int) env('OBS_CIRCUIT_FAILURE_THRESHOLD', 5),
          'open_seconds' => (int) env('OBS_CIRCUIT_OPEN_SECONDS', 30),
          'half_open_after_seconds' => (int) env('OBS_CIRCUIT_HALF_OPEN_AFTER_SECONDS', 30),
      ],
      'slow_query_ms' => (int) env('OBS_SLOW_QUERY_MS', 250),
      'health' => ['queue_stale_after_seconds' => (int) env('OBS_QUEUE_STALE_AFTER_SECONDS', 60)],
  ];
  ```

  In `back/config/logging.php`, import `JsonFormatter` and `RotatingFileHandler`, then add these channels. Keep `single` and `daily` untouched for local compatibility:

  ```php
  'json' => [
      'driver' => 'monolog',
      'handler' => RotatingFileHandler::class,
      'handler_with' => ['filename' => storage_path('logs/application.json'), 'maxFiles' => env('LOG_DAILY_DAYS', 14)],
      'formatter' => JsonFormatter::class,
      'level' => env('LOG_LEVEL', 'info'),
  ],
  'audit' => [
      'driver' => 'monolog',
      'handler' => RotatingFileHandler::class,
      'handler_with' => ['filename' => storage_path('logs/audit.json'), 'maxFiles' => 90, 'filePermission' => 0640],
      'formatter' => JsonFormatter::class,
      'level' => 'info',
  ],
  'health' => [
      'driver' => 'monolog',
      'handler' => RotatingFileHandler::class,
      'handler_with' => ['filename' => storage_path('logs/health.json'), 'maxFiles' => 30, 'filePermission' => 0640],
      'formatter' => JsonFormatter::class,
      'level' => 'info',
  ],
  ```

  Document `LOG_STACK=json`, each `OBS_*` setting, and the non-secret logging policy in `.env.example`. Do not place a production credential or a real path outside `storage/logs` in the file.

- [ ] **Step 4: Make audit projection redaction pass**

  Modify `AuditService::log()` to keep its existing `audit_logs` insert intact, then write only a safe projection after it succeeds:

  ```php
  Log::channel('audit')->info('audit.event', [
      'event_type' => $action,
      'actor_user_id' => $actorUserId ?? auth()->id(),
      'resource_type' => $resourceType,
      'resource_id' => $resourceId,
      'request_id' => $request?->attributes->get('request_id'),
      'ip_address' => $request?->ip(),
      'metadata_keys' => array_keys($metadata ?? []),
      'value_changed' => $metadata !== null && (array_key_exists('old_value', $metadata) || array_key_exists('new_value', $metadata)),
  ]);
  ```

  Use `request_id` only from the request attribute established in Task 2; do not re-read an unvalidated header.

- [ ] **Step 5: Run focused tests and commit**

  Run:

  ```powershell
  Set-Location back
  php artisan test --filter="LoggingConfigurationTest|LoggingRedactionTest"
  ```

  Expected: PASS; channels are JSON-configured and audit values/secrets are absent from captured context.

  ```powershell
  git add back/config/observability.php back/config/logging.php back/.env.example back/app/Services/AuditService.php back/tests/Feature/Api/LoggingRedactionTest.php back/tests/Feature/Observability/LoggingConfigurationTest.php
  git commit -m "feat: configure structured observability logs"
  ```

### Task 2: Add correlation context and safe exception/slow-query diagnostics

**Files:**
- Create: `back/app/Http/Middleware/AssignRequestContext.php`
- Create: `back/app/Support/ObservabilityContext.php`
- Create: `back/app/Support/SafeExceptionContext.php`
- Create: `back/app/Support/SlowQueryContext.php`
- Modify: `back/bootstrap/app.php:25-95`
- Modify: `back/app/Http/Controllers/Api/HealthController.php:11-33`
- Modify: `back/app/Http/Middleware/TrackApiPerformance.php:17-42`
- Create: `back/tests/Feature/Observability/RequestContextTest.php`
- Create: `back/tests/Unit/Support/SafeExceptionContextTest.php`

**Interfaces:**
- `AssignRequestContext::handle(Request, Closure): Response` sets `$request->attributes->get('request_id')`, calls `Log::withContext(['request_id' => $requestId])`, and returns `X-Request-Id`.
- `ObservabilityContext::forStreamEvent(string $correlationId, array $context, Closure $callback): mixed` scopes and clears worker event context in a long-running process.
- `SafeExceptionContext::from(Throwable $e): array{exception_class:string,exception_code:string|int,exception_fingerprint:string,previous_exception_class:?string}` never returns message text or a trace by default.

- [ ] **Step 1: Write failing HTTP and sanitization tests**

  Create `RequestContextTest`:

  ```php
  public function test_valid_uuid_is_propagated_to_response_and_logs(): void
  {
      $requestId = '018f3d4b-0b14-7d4f-899f-8865b38cc3a1';
      $this->withHeader('X-Request-Id', $requestId)->getJson('/api/health')
          ->assertOk()
          ->assertHeader('X-Request-Id', $requestId);

      $healthLog = collect($this->captured)->firstWhere('message', 'Health check request');
      $this->assertSame($requestId, $healthLog?->context['request_id']);
  }

  public function test_invalid_or_oversized_request_id_is_replaced(): void
  {
      $response = $this->withHeader('X-Request-Id', str_repeat('x', 1024))->getJson('/api/health')->assertOk();
      $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f-]{27}$/', (string) $response->headers->get('X-Request-Id'));
  }
  ```

  Create `SafeExceptionContextTest` with a nested exception whose message contains `API_KEY_SENTINEL`; assert the result contains class/fingerprint/previous class but `json_encode($context)` does not contain the sentinel, `getTraceAsString()`, or a `message` key.

- [ ] **Step 2: Run tests and verify expected failure**

  ```powershell
  Set-Location back
  php artisan test --filter="RequestContextTest|SafeExceptionContextTest"
  ```

  Expected: FAIL because the application currently reflects/generates request IDs independently at call sites and reporters include ad hoc exception strings.

- [ ] **Step 3: Implement request and worker context ownership**

  Implement the middleware with strict UUID validation and one context owner:

  ```php
  $incoming = (string) $request->header('X-Request-Id', '');
  $requestId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $incoming)
      ? strtolower($incoming)
      : (string) Str::uuid7();

  $request->attributes->set('request_id', $requestId);
  Log::withContext(['request_id' => $requestId]);
  $response = $next($request);
  $response->headers->set('X-Request-Id', $requestId);

  return $response;
  ```

  Register it as the first API middleware in `bootstrap/app.php`. Update `HealthController` and `TrackApiPerformance` to use `$request->attributes->get('request_id')` and delete their `uniqid()`/raw-header fallbacks. Do not edit every controller: request-wide `Log::withContext` enriches existing `Log::*` calls without duplicated arrays.

  Implement `ObservabilityContext` using `Log::withContext()` before a callback and `Log::withoutContext()` in `finally`; its stream-event helper must include only `correlation_id`, `stream`, `stream_id`, `consumer`, and fixed event identifiers. Use this helper around each `handleMessage()` call in the three Redis consumers so daemon contexts cannot bleed into the next message.

- [ ] **Step 4: Replace unsafe exception and slow-query detail**

  `SafeExceptionContext` must fingerprint exception data rather than dump it:

  ```php
  return [
      'exception_class' => $e::class,
      'exception_code' => $e->getCode(),
      'exception_fingerprint' => hash('sha256', $e::class.'|'.$e->getCode().'|'.$e->getFile().'|'.$e->getLine()),
      'previous_exception_class' => $e->getPrevious() ? $e->getPrevious()::class : null,
  ];
  ```

  In `bootstrap/app.php`, replace raw exception-message reporter context with this array and request attributes. Register `DB::whenQueryingForLongerThan(config('observability.slow_query_ms'), function (Connection $connection, QueryExecuted $query): void { Log::warning('database.slow_query', SlowQueryContext::from($query)); })` in `AppServiceProvider::boot()`; `SlowQueryContext` must return `duration_ms`, `sql_fingerprint`, `statement` (`select|insert|update|delete|other`), `tables` (schema identifiers only), and `binding_types`. It must set `rows_examined` to `null` with `rows_examined_source: 'not_available_in_application_query_event'` rather than execute `EXPLAIN`.

  If development-only stack detail is desired, gate the normalized top frames behind `LOG_EXCEPTION_TRACE=true`; production default is false, and never include exception messages or argument values.

- [ ] **Step 5: Run focused tests and commit**

  ```powershell
  Set-Location back
  php artisan test --filter="RequestContextTest|SafeExceptionContextTest|LoggingRedactionTest"
  ```

  Expected: PASS; invalid request IDs are replaced, all API logs receive a stable ID, and sanitized contexts cannot reveal the sentinel.

  ```powershell
  git add back/app/Http/Middleware/AssignRequestContext.php back/app/Support/ObservabilityContext.php back/app/Support/SafeExceptionContext.php back/app/Support/SlowQueryContext.php back/bootstrap/app.php back/app/Providers/AppServiceProvider.php back/app/Http/Controllers/Api/HealthController.php back/app/Http/Middleware/TrackApiPerformance.php back/tests/Feature/Observability/RequestContextTest.php back/tests/Unit/Support/SafeExceptionContextTest.php
  git commit -m "feat: add correlated safe diagnostics"
  ```

### Task 3: Bound operational log volume and record HTTP rate-limit events

**Files:**
- Create: `back/app/Support/OperationalLogger.php`
- Create: `back/app/Http/Middleware/LogRateLimitedResponse.php`
- Modify: `back/bootstrap/app.php:25-48`
- Modify: `back/app/Http/Middleware/TrackApiPerformance.php:17-42`
- Modify: `back/app/Providers/AppServiceProvider.php:27-79`
- Create: `back/tests/Feature/Observability/OperationalLoggerTest.php`
- Create: `back/tests/Feature/Observability/RateLimitLoggingTest.php`

**Interfaces:**
- `OperationalLogger::warning(string $event, string $category, array $context = [], bool $critical = false): void` accepts only declared fixed categories; it emits the first N events/window and one `observability.log_suppressed` summary per category/window.
- `LogRateLimitedResponse::handle(Request, Closure): Response` logs only throttle-generated 429 responses and preserves their headers/body exactly.
- The event schema uses `event`, `category`, `rate_limit_scope`, `limit`, `remaining`, `retry_after_seconds`, `route`, `actor_type`, `actor_id_or_ip_hash`, and `request_id`; it never logs the internal RateLimiter cache key or a raw client IP in high-volume records.

- [ ] **Step 1: Write failing bounded-logging tests**

  In `OperationalLoggerTest`, freeze time, call the same category 21 times, and assert 20 event records plus one summary with `suppressed_count: 1`; then advance 61 seconds and assert the next event is emitted normally. Also assert `critical: true` produces every event.

  In `RateLimitLoggingTest`, override `api-read` to `Limit::perMinute(1)`, send two `/api/health` requests, and assert the captured 429 event has only safe fields:

  ```php
  $event = collect($this->captured)->firstWhere('message', 'http.rate_limited');
  $this->assertSame('api', $event?->context['rate_limit_scope']);
  $this->assertSame(1, $event?->context['limit']);
  $this->assertSame(0, $event?->context['remaining']);
  $this->assertArrayHasKey('retry_after_seconds', $event?->context ?? []);
  $this->assertArrayNotHasKey('rate_limiter_key', $event?->context ?? []);
  ```

- [ ] **Step 2: Run tests and verify expected failure**

  ```powershell
  Set-Location back
  php artisan test --filter="OperationalLoggerTest|RateLimitLoggingTest"
  ```

  Expected: FAIL because no bounded logger or 429 observer exists.

- [ ] **Step 3: Implement the bounded logger and global 429 observer**

  `OperationalLogger` must use a cache key composed only from `observability-log`, a whitelist category, and the current minute bucket. Keep a sibling integer for suppressed events. Its core behavior is:

  ```php
  if ($critical || RateLimiter::attempt($key, $maxEvents, $callback, $windowSeconds)) {
      Log::warning($event, $context + ['category' => $category]);
      return;
  }

  $suppressed = Cache::increment("{$key}:suppressed");
  if ($suppressed === 1) {
      Log::warning('observability.log_suppressed', ['category' => $category, 'suppressed_count' => 1]);
  }
  ```

  Ensure the first suppressed event is represented by a summary and no raw dynamic identifier becomes part of the cache key. Apply it to `TrackApiPerformance` debug start/complete records, health request records, and recurring consumer run-loop entry/completion/slow records. Do not apply it to `audit.event`, terminal DLQ events, or circuit state transitions.

  Register `LogRateLimitedResponse` globally after request context. It logs only when `$response->getStatusCode() === 429` and `X-RateLimit-Limit` is present. Its context uses the route URI (not an ID-bearing full URL), standard response headers, a SHA-256 prefix of IP only for anonymous callers, and `request_id`. It must return the unmodified response.

- [ ] **Step 4: Add rate-limit response callbacks where Laravel provides richer facts**

  In each existing limiter in `AppServiceProvider`, attach `response()` to the `Limit` instances so a limiter rejection emits the exact logical scope (`api-read`, `api-write`, or `auth-login`) before Laravel returns its normal `429` JSON. Preserve the existing rate/bucket values and response shape:

  ```php
  Limit::perMinute(120)
      ->by($identifier)
      ->response(fn (Request $request, array $headers) => app(OperationalLogger::class)
          ->rateLimited($request, 'api-read', $headers));
  ```

  The callback must return Laravel's default throttle response after logging; do not use request email, API key, or raw limiter bucket in the context. The global response middleware remains a fallback only for numeric `throttle:N,1` routes.

- [ ] **Step 5: Run focused tests and commit**

  ```powershell
  Set-Location back
  php artisan test --filter="OperationalLoggerTest|RateLimitLoggingTest|SecurityRateLimitTest|RateLimitSecurityTest"
  ```

  Expected: PASS; existing rate-limit behavior is unchanged and 429 telemetry is bounded/redacted.

  ```powershell
  git add back/app/Support/OperationalLogger.php back/app/Http/Middleware/LogRateLimitedResponse.php back/bootstrap/app.php back/app/Http/Middleware/TrackApiPerformance.php back/app/Providers/AppServiceProvider.php back/tests/Feature/Observability/OperationalLoggerTest.php back/tests/Feature/Observability/RateLimitLoggingTest.php
  git commit -m "feat: bound operational logs and record throttling"
  ```

### Task 4: Add retry-safe circuit breakers to remote dependency boundaries

**Files:**
- Create: `back/app/Support/CircuitBreaker.php`
- Create: `back/app/Exceptions/CircuitOpenException.php`
- Modify: `back/app/Services/Ingestion/RawSensorEventPublisher.php:19-86`
- Modify: `back/app/Services/Ingestion/DomainEventPublisher.php:24-88`
- Modify: `back/app/Services/Ingestion/SensorReadingProjectionService.php:17-161`
- Modify: `back/app/Services/Ingestion/DomainEventBroadcastConsumer.php:172-263`
- Create: `back/tests/Unit/Support/CircuitBreakerTest.php`
- Modify: `back/tests/Unit/EventPublisherXaddShapeTest.php`
- Modify: `back/tests/Feature/DomainEventBroadcastConsumerTest.php`

**Interfaces:**
- `CircuitBreaker::call(string $dependency, Closure $operation): mixed` returns the operation result; throws `CircuitOpenException` before invoking it when open; transitions `closed → open → half_open → closed` using fixed dependency names.
- Publishers continue returning `false` on Redis failure/open circuit; CDC consumer therefore retains pending messages. Cache projection continues its current best-effort failure behavior. Reverb open circuit is handled as a consumer failure so it remains pending.
- State events are `circuit.closed`, `circuit.opened`, `circuit.half_open`, and `circuit.rejected`, each containing dependency, failure count, cooldown, and request/correlation context but no endpoint secrets.

- [ ] **Step 1: Write failing state-machine and non-loss tests**

  Create unit tests with `Cache::fake()` or a deterministic cache repository:

  ```php
  public function test_open_breaker_skips_operation_until_one_half_open_probe_succeeds(): void
  {
      $breaker = app(CircuitBreaker::class);
      config(['observability.circuit_breakers.failure_threshold' => 2, 'observability.circuit_breakers.open_seconds' => 30]);

      try { $breaker->call('redis_stream', fn () => throw new RuntimeException('down')); } catch (RuntimeException) {}
      try { $breaker->call('redis_stream', fn () => throw new RuntimeException('down')); } catch (RuntimeException) {}
      try {
          $breaker->call('redis_stream', fn () => $this->fail('open breaker must not invoke operation'));
          $this->fail('open breaker must reject the call');
      } catch (CircuitOpenException) {
          $this->assertTrue(true);
      }
  }
  ```

  Add a CDC/domain consumer test which opens the `reverb` circuit before a delivery, runs one iteration, and proves the record is `pending` (not ACKed, not marked `delivered_at`, not DLQed before its existing maximum-attempt rule).

- [ ] **Step 2: Run focused tests and verify expected failure**

  ```powershell
  Set-Location back
  php artisan test --filter="CircuitBreakerTest|EventPublisherXaddShapeTest|DomainEventBroadcastConsumerTest"
  ```

  Expected: FAIL because no breaker exists and remote calls are always attempted.

- [ ] **Step 3: Implement a conservative state machine**

  Require a whitelist dependency name, store state under `observability:circuit:{dependency}`, and use a cache lock when available. The state contract is:

  ```php
  ['state' => 'closed|open|half_open', 'failure_count' => 0, 'opened_at' => null, 'probe_in_flight' => false]
  ```

  On a thrown operation, increment `failure_count`; at threshold set `state: open`, `opened_at: now()`, and emit unsuppressed `circuit.opened`. Before cooldown expiry, throw `CircuitOpenException`; after expiry admit exactly one half-open probe. A successful probe clears state and emits `circuit.closed`; a failed probe reopens it. If cache/lock infrastructure itself fails, emit one critical `circuit.state_store_unavailable` and execute the operation (fail-visible, never silently drop work).

- [ ] **Step 4: Apply breakers at the correct boundaries**

  Wrap only these calls:

  ```php
  // RawSensorEventPublisher
  $this->breaker->call('redis_stream', fn () => $this->rawXadd(
      Redis::connection('default'), $streamName, $maxLength, $fields,
      $minAgeMs > 0 ? $minAgeMs : null,
  ));

  // SensorReadingProjectionService cache pipeline
  $this->breaker->call('redis_cache', fn () => Redis::pipeline(function ($pipe) use ($key, $payload): void {
      $pipe->lpush($key, $payload);
      $pipe->ltrim($key, 0, self::CACHE_LIMIT - 1);
  }));

  // DomainEventBroadcastConsumer before event($event)
  $this->breaker->call('reverb', fn () => $this->broadcastFact($outbox));
  ```

  Inject `CircuitBreaker` through constructors and update existing tests' construction helpers. Catch `CircuitOpenException` alongside existing remote `Throwable` paths only where the current contract already says “return false,” “skip cache,” or “leave pending.” Do not wrap `DB::transaction`, `AuditService`, raw-event normalization, DLQ append, ACK, or status persistence.

- [ ] **Step 5: Run focused tests and commit**

  ```powershell
  Set-Location back
  php artisan test --filter="CircuitBreakerTest|EventPublisherXaddShapeTest|RawStreamConsumerTest|CdcOutboxStreamConsumerTest|DomainEventBroadcastConsumerTest"
  ```

  Expected: PASS; an open dependency stops repeated calls temporarily but preserves the pre-existing retry/DLQ semantics.

  ```powershell
  git add back/app/Support/CircuitBreaker.php back/app/Exceptions/CircuitOpenException.php back/app/Services/Ingestion/RawSensorEventPublisher.php back/app/Services/Ingestion/DomainEventPublisher.php back/app/Services/Ingestion/SensorReadingProjectionService.php back/app/Services/Ingestion/DomainEventBroadcastConsumer.php back/tests/Unit/Support/CircuitBreakerTest.php back/tests/Unit/EventPublisherXaddShapeTest.php back/tests/Feature/DomainEventBroadcastConsumerTest.php
  git commit -m "feat: protect remote pipeline dependencies with breakers"
  ```

### Task 5: Make retry, DLQ, cache, worker, and audit transitions queryable in logs

#### Execution evidence — 2026-09-21 (blocked before production changes)

- Added the first TDD artifact: `back/tests/Feature/Observability/AuditTrailTest.php`. It specifies three independent low-risk audit outcomes: general configuration records only changed setting keys; email configuration records the password-setting change without the password in either `audit_logs` metadata or captured logs; device-key rotation records the actor and device without key material.
- `php -l back/tests/Feature/Observability/AuditTrailTest.php` passed: the new regression test is syntactically valid.
- `Set-Location back; php artisan test --filter=AuditTrailTest` cannot bootstrap because `back/vendor/autoload.php` is absent. Composer was downloaded locally and retried with both archive and source installation modes; package downloads completed but Composer could not extract packages and never generated `vendor/autoload.php`.
- No application source has been changed. This preserves the required RED-GREEN order: `AuditService`, `ConfigController`, `EmailConfigController`, `DeviceApiController`, and consumer commands remain untouched until the regression can fail against a bootable Laravel application.

**Files:**
- Modify: `back/app/Services/Ingestion/RawStreamConsumer.php:72-294`
- Modify: `back/app/Services/Ingestion/Cdc/CdcOutboxStreamConsumer.php:107-425`
- Modify: `back/app/Services/Ingestion/DomainEventBroadcastConsumer.php:95-525`
- Modify: `back/app/Services/Ingestion/DeadLetterStreamService.php:31-142`
- Modify: `back/app/Services/Ingestion/SensorReadingProjectionService.php:17-161`
- Modify: `back/app/Console/Commands/ConsumeRawEvents.php:30-90`
- Modify: `back/app/Console/Commands/ConsumeCdcOutboxes.php:37-113`
- Modify: `back/app/Console/Commands/ConsumeDomainEvents.php:29-90`
- Modify: `back/app/Services/AuditService.php:14-99`
- Modify: `back/app/Http/Controllers/Api/ConfigController.php:112-226`
- Modify: `back/app/Http/Controllers/Api/EmailConfigController.php:39-136`
- Modify: `back/app/Http/Controllers/Api/DeviceApiController.php:442-478`
- Create: `back/tests/Feature/Observability/RetryAndAuditLoggingTest.php`

**Interfaces:**
- Retry event context is exactly `stream`, `stream_id`, `consumer_group`, `attempt_number`, `next_retry_after_ms`, `failure_category`, `exception_class`, and correlation/event ID when present.
- DLQ event context adds `dlq_stream`, `terminal: true`, and a fixed failure category (`validation`, `missing_reference`, `database`, `redis`, `broadcast`, `external`, `unknown`), never the raw payload/exception message.
- Lifecycle events use `worker.started`/`worker.stopping`/`worker.stopped`; cache events use `cache.hit`, `cache.miss`, `cache.error` with `cache_key_pattern` and `ttl_seconds`, never a key containing sensor/device IDs.

- [ ] **Step 1: Write failing event-schema tests**

  Add `RetryAndAuditLoggingTest` that forces one normalizer failure, captures `RawStreamConsumer: retry scheduled`, and asserts:

  ```php
  $this->assertSame(1, $retry->context['attempt_number']);
  $this->assertSame(30000, $retry->context['next_retry_after_ms']);
  $this->assertSame('database', $retry->context['failure_category']);
  $this->assertArrayNotHasKey('payload_json', $retry->context);
  ```

  Exercise role change, device key rotation, alert/general config update, and email config update. Assert one `audit.event` per successful change includes action/actor/resource/request ID but no `api_key`, `password`, secret setting value, old value, or new value.

- [ ] **Step 2: Run focused tests and verify expected failure**

  ```powershell
  Set-Location back
  php artisan test --filter="RetryAndAuditLoggingTest|LoggingRedactionTest"
  ```

  Expected: FAIL because retries omit their computed delay/category and several sensitive operations have no `AuditService` call.

- [ ] **Step 3: Add shared failure classification and retry/DLQ events**

  Add a private fixed classifier in each consumer (or a shared `FailureCategory` support class if all three can use it without circular dependencies): map validation/malformed messages to `validation`, missing business rows to `missing_reference`, `QueryException`/`PDOException` to `database`, `CircuitOpenException`/Redis errors to `redis`, Reverb errors to `broadcast`, and everything else to `unknown`.

  Before every existing pending return, emit the same bounded event with a deterministic delay equal to that consumer command's configured claim-idle value (currently `30000` ms by Compose default), not an invented exponential schedule:

  ```php
  app(OperationalLogger::class)->warning('stream.retry_scheduled', 'stream_retry', array_merge([
      'stream' => $this->stream,
      'stream_id' => $id,
      'consumer_group' => $this->group,
      'attempt_number' => $attempts,
      'next_retry_after_ms' => $claimIdleMs,
      'failure_category' => $this->failureCategory($e),
  ], SafeExceptionContext::from($e)));
  ```

  Thread `claimIdleMs` from `runOnce()` into `handleMessage()`; do not hard-code it. On DLQ append, emit an unsuppressed `stream.dead_lettered` record with the same safe context and no `payload_json`.

- [ ] **Step 4: Add lifecycle/cache/audit events without duplicating sensitive data**

  In each consume command, log `worker.started` after configuration validation and `worker.stopping` in the signal path, with `worker`, `consumer`, stream/group, PID, and service version. Add `worker.stopped` once the loop exits. In `SensorReadingProjectionService`, log `cache.hit`/`cache.miss`/`cache.error` through `OperationalLogger`; use a literal pattern such as `sensor_readings:latest:{sensor_id}` and its configured TTL, never the resolved Redis key.

  Inject `AuditService` into `ConfigController`, `EmailConfigController`, and the existing `DeviceApiController`; call its existing semantic methods after a successful mutation. For grouped configuration writes, record each changed setting key as an audit record with `value_changed: true` but do not pass/display values. Preserve the current durable `audit_logs` insert and controller responses.

- [ ] **Step 5: Run focused tests and commit**

  ```powershell
  Set-Location back
  php artisan test --filter="RetryAndAuditLoggingTest|LoggingRedactionTest|RawStreamConsumerTest|CdcOutboxStreamConsumerTest|DomainEventBroadcastConsumerTest"
  ```

  Expected: PASS; retries say when and why they recur, terminal events are never sampled out, and sensitive actions produce safe audit records.

  ```powershell
  git add back/app/Services/Ingestion/RawStreamConsumer.php back/app/Services/Ingestion/Cdc/CdcOutboxStreamConsumer.php back/app/Services/Ingestion/DomainEventBroadcastConsumer.php back/app/Services/Ingestion/DeadLetterStreamService.php back/app/Services/Ingestion/SensorReadingProjectionService.php back/app/Console/Commands/ConsumeRawEvents.php back/app/Console/Commands/ConsumeCdcOutboxes.php back/app/Console/Commands/ConsumeDomainEvents.php back/app/Services/AuditService.php back/app/Http/Controllers/Api/ConfigController.php back/app/Http/Controllers/Api/EmailConfigController.php back/app/Http/Controllers/Api/DeviceApiController.php back/tests/Feature/Observability/RetryAndAuditLoggingTest.php
  git commit -m "feat: expose safe pipeline retry and audit events"
  ```

### Task 6: Implement scheduled dependency, queue, and pipeline health reporting

**Files:**
- Create: `back/app/Services/Monitoring/OperationalHealthService.php`
- Create: `back/app/Console/Commands/RunObservabilityHealthCheck.php`
- Modify: `back/routes/console.php:1-9`
- Modify: `docker-compose.yml` (add `scheduler` service and observability environment to worker anchor)
- Modify: `back/app/Services/Monitoring/EventPipelineMetricsService.php:71-139`
- Create: `back/tests/Feature/Observability/HealthCheckCommandTest.php`
- Modify: `back/tests/Feature/EventPipelineMetricsServiceTest.php`

**Interfaces:**
- `OperationalHealthService::check(): array{status:'ok'|'degraded',checks:array<string,array{available:bool,status:string,details:array}>}`.
- `observability:health` writes one `health.check.completed` record to the `health` channel and returns `SUCCESS` only when every required dependency is available.
- Health checks: DB `SELECT 1`; Redis `PING`; database queue count and oldest available-job age; `EventPipelineMetricsService::snapshot()`; Reverb TCP reachability; MySQL counters only when the DB driver is MySQL.

- [ ] **Step 1: Write failing health command tests**

  Create a command test that fakes an all-healthy service and asserts status 0 plus one health-channel event containing `database`, `redis`, `queue`, `pipeline`, `reverb`, and `mysql_connections`. Create a second test that makes Redis unavailable and asserts status 1, `status: degraded`, `checks.redis.available: false`, and no exception text in the log context.

  Extend `EventPipelineMetricsServiceTest` to assert the snapshot includes a top-level `observability` map populated from `CircuitBreaker::snapshot()` for `redis_stream`, `redis_cache`, and `reverb` with `state` but not state-store cache keys.

- [ ] **Step 2: Run focused tests and verify expected failure**

  ```powershell
  Set-Location back
  php artisan test --filter="HealthCheckCommandTest|EventPipelineMetricsServiceTest"
  ```

  Expected: FAIL because no scheduled command/operational health surface exists.

- [ ] **Step 3: Implement portable probes and bounded MySQL-only counters**

  `OperationalHealthService` must catch each dependency failure independently and return `available: false` with `error_class` and a hash fingerprint, not a message. For database queues query `jobs` only when `config('queue.default') === 'database'`; return `not_applicable` otherwise. Calculate queue age from `MIN(available_at)` and current epoch; do not assume a Laravel worker exposes a pool metric.

  For MySQL, use exactly these read-only queries and normalize numerics:

  ```php
  $status = collect(DB::select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Max_used_connections')"))
      ->mapWithKeys(fn ($row) => [$row->Variable_name => (int) $row->Value]);
  $max = (int) DB::selectOne("SHOW VARIABLES LIKE 'max_connections'")->Value;
  ```

  On SQLite/PostgreSQL, return `{available: false, status: 'not_supported_for_driver', details: ['driver' => $driver]}` for only the MySQL counters; the overall DB probe may still be healthy.

- [ ] **Step 4: Wire scheduler deployment and health logging**

  Add to `routes/console.php`:

  ```php
  Schedule::command('observability:health')
      ->everyMinute()
      ->withoutOverlapping()
      ->onOneServer();
  ```

  In Compose, add a `scheduler` service built from `back/Dockerfile`, reusing `*worker_env`, running `php artisan schedule:work`, with `restart: unless-stopped`, profile `workers`, and the same DB/Redis/Reverb dependencies as `domain-event-consumer`. `onOneServer()` requires the shared configured cache in production; document that deployment must use a shared atomic cache driver or omit `onOneServer` for a single scheduler replica. Do not add a redundant HTTP probe or change `/api/health`'s response contract.

- [ ] **Step 5: Run focused tests and commit**

  ```powershell
  Set-Location back
  php artisan test --filter="HealthCheckCommandTest|EventPipelineMetricsServiceTest"
  php artisan observability:health
  ```

  Expected: tests pass; the local command returns 0 only with its configured dependencies reachable, otherwise returns 1 and writes a structured degraded event.

  ```powershell
  git add back/app/Services/Monitoring/OperationalHealthService.php back/app/Console/Commands/RunObservabilityHealthCheck.php back/routes/console.php docker-compose.yml back/app/Services/Monitoring/EventPipelineMetricsService.php back/tests/Feature/Observability/HealthCheckCommandTest.php back/tests/Feature/EventPipelineMetricsServiceTest.php
  git commit -m "feat: add scheduled operational health checks"
  ```

### Task 7: Verify end-to-end configuration, safety, and operational behavior

**Files:**
- Modify: `README.md` (backend operations/logging section)
- Create: `docs/implementation/backend-observability-runbook.md`
- Modify: `back/.env.example`

**Interfaces:**
- Documents JSON field vocabulary, retention, what constitutes degraded health, breaker reset/recovery, rate-limit log interpretation, and commands to inspect logs without printing secrets.
- Produces reproducible verification evidence without modifying business data.

- [ ] **Step 1: Write the operator runbook**

  Document these exact actions and expected observations:

  ```powershell
  Set-Location back
  php artisan observability:health
  Get-Content storage/logs/health-*.json -Tail 1
  Get-Content storage/logs/audit-*.json -Tail 1
  ```

  Include state meanings: `closed` means calls are admitted; `open` means calls are skipped until cooldown; `half_open` permits one probe. State clearly that an open stream/reverb breaker is a delivery delay, not successful processing, and operators should inspect pending/DLQ metrics before manually replaying via the existing `dlq:inspect` / `dlq:replay` flow.

- [ ] **Step 2: Run the complete focused regression suite**

  ```powershell
  Set-Location back
  php artisan test --filter="LoggingRedactionTest|LoggingConfigurationTest|RequestContextTest|SafeExceptionContextTest|OperationalLoggerTest|RateLimitLoggingTest|CircuitBreakerTest|RetryAndAuditLoggingTest|HealthCheckCommandTest|SecurityRateLimitTest|RateLimitSecurityTest|EventPipelineMetricsServiceTest|RawStreamConsumerTest|CdcOutboxStreamConsumerTest|DomainEventBroadcastConsumerTest"
  php artisan config:clear
  php artisan config:cache
  php artisan optimize:clear
  ```

  Expected: all runnable tests pass. Redis-backed stream tests may report their existing explicit `ENVIRONMENT_CONSTRAINT` skip when no Redis test server is configured; do not convert that skip into a fake unit pass.

- [ ] **Step 3: Verify Compose topology and JSON output**

  Run:

  ```powershell
  docker compose --profile workers config
  docker compose --profile workers up -d scheduler
  docker compose --profile workers logs --tail=100 scheduler
  ```

  Expected: configuration includes exactly one scheduler service, it remains running, and it invokes `observability:health` once per minute. In a safe local environment, send a request with a valid UUID and confirm `X-Request-Id` matches a JSON log record. Do not include token/API-key values in command output or documentation.

- [ ] **Step 4: Review the final diff for scope violations**

  ```powershell
  git diff --check
  git diff -- back/app back/bootstrap/app.php back/config back/routes/console.php docker-compose.yml back/tests README.md docs/implementation
  ```

  Expected: no migration, API response, authorization, or domain-transition change; all new state is cache/log/config only; no log field includes prohibited secrets or raw values.

- [ ] **Step 5: Commit documentation and verification-only changes**

  ```powershell
  git add README.md docs/implementation/backend-observability-runbook.md back/.env.example
  git commit -m "docs: add backend observability runbook"
  ```

## Self-Review

- **Spec coverage:** Correlation IDs (Task 2), health/connection/queue metrics (Task 6), exception and slow-query safety (Task 2), audit (Tasks 1 and 5), retry/DLQ/cache/lifecycle (Task 5), rate limiting and log-volume control (Task 3), circuit breakers (Task 4), JSON output (Task 1), and runbook/verification (Task 7) each have an owning task.
- **Safety resolution:** The audit’s suggestions to log full traces, bind parameters, raw query plans, raw cache keys, and setting values are deliberately constrained by the pre-existing secret-redaction contract. The plan uses class/fingerprint/type/schema metadata instead; production diagnostics must not create a new exfiltration surface.
- **Type consistency:** `request_id` is HTTP UUID context; `correlation_id` is stream-event/outbox identity; `event` is the structured operation name. All low-cardinality breaker names are `redis_stream`, `redis_cache`, and `reverb`.
- **Review-focus tests:** Tasks 2–6 each add the corresponding hostile input, outage/non-loss, 429, sensitive-audit, and degraded-health test described above.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-09-21-backend-observability-logging.md`. Please review the plan. Which execution approach would you prefer?

- **Subagent-driven** — A fresh subagent implements each task and a fresh reviewer checks it before the next one starts, then a whole-branch review at the end. Most thorough; costs a fresh context per task and per review.
- **Native** — I implement every task myself in this session, then one fresh reviewer checks the whole branch. Cheapest and fastest; the plan carries the detailed interfaces and tests.

For this plan I recommend **Subagent-driven**, because it changes cross-cutting failure handling, tests critical non-loss behavior, and requires disciplined security review of every new log field.
