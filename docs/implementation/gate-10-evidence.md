# Gate 10 Evidence

Status: IN PROGRESS

HEAD:
<to be filled from `git rev-parse HEAD` after commit — planning baseline was bf75de2>

> **Session constraint (why fields below are unverified):** the implementation session that wrote
> this code had NO reachable `php`, `composer`, `mysql`, `docker`, or `redis`, and the Git Bash
> shell faulted on process fork, so `vitest`/`node` could not be launched either. Every code and
> config artifact below was WRITTEN and cross-checked by reading, but NOT executed. No field is
> marked green from prose or mocks. An operator must run the commands in section "Validation order"
> (PLAN.md §14) on a live stack and paste real output into each slot before any PASS verdict.

## Artifacts written (source of truth = the repo diff)

| Task | Artifact | State |
|------|----------|-------|
| 1 | `back/app/Services/Ingestion/RawReadingNormalizer.php` + test | written (Unicode canonicalizer, ambiguous-name guard, one-query grouping) — NOT run |
| 3 | `docker-compose.yml` db ROW binlog; `infra/mysql/init/01-create-cdc-user.sh` | written — NOT run |
| 4 | `cdc/application.properties`; `debezium` compose service | written — NOT run |
| 5 | `back/app/Services/Ingestion/Cdc/DebeziumChange.php`, `CdcOutboxStreamConsumer.php`, `back/app/Console/Commands/ConsumeCdcOutboxes.php`, `config/app.php` keys, `.env.example`, `CdcOutboxStreamConsumerTest.php` | written — NOT run |
| 6 | Removed relay dispatch from `IngestionController`/`DomainEventRecorder`; deleted 6 relay files; removed 2 compose relay services; added `outbox-cdc-consumer`; `Gate10NoPollingArchitectureTest.php` | written — NOT run |
| 7 | `EventPipelineMetricsService.php`, `InternalMetricsController::eventPipeline`, route, `EventPipelineMetricsServiceTest.php` | written (subagent) — NOT run |
| 8 | `front/scripts/verify-no-polling.mjs`, `package.json` script | written — NOT run |
| 9 | `network-assertion.mjs` marked SIMULATED; `network-assertion-live.mjs` | written — NOT run |
| 10 | `Gate10PublicGraphBoundaryTest.php` | written (subagent) — NOT run |
| 12 | `.github/workflows/gate10-quality.yml` | written — NOT run |

## 10.1 Observability
`EventPipelineMetricsService::snapshot()` returns `streams`/`consumers`/`outbox`; counters
`cdc_publish_success|cdc_publish_failure|cdc_dlq|domain_broadcast_success|domain_broadcast_failure|raw_processed|raw_failed`.
Endpoint `GET /api/internal/metrics/event-pipeline` gated `auth:sanctum` + `admin` + `throttle:api-read`.
Evidence: <admin 200 / user 403 / anon 401 test output — NOT YET CAPTURED>

## 10.2 Legacy retirement
Static gate `front/scripts/verify-no-polling.mjs` fails on `setInterval(` in `front/src`/Blade,
retired `/dashboard/public` `/config/public`, and `ingestion:relay-outbox`/`domain:relay-outbox`/
`--interval` sweeps. Production `front/src` scan showed `setInterval(` only in `*.test.js`
fake-timer strings (excluded).
Evidence: <`npm run audit:no-polling:source` output — NOT YET CAPTURED>

## 10.3 Deployment-wide no-polling proof
`network-assertion-live.mjs` (no mocks): guest 35s quiet + allowed-anon-only; authenticated 35s
quiet; visibilitychange -> one bounded burst; reconnect -> one recovery sequence; logout -> stale
token 401.
Evidence: <`front/.audit-e2e/results/gate10-live-network.json` — NOT YET CAPTURED>

## 10.4 Public graph boundary
`Gate10PublicGraphBoundaryTest.php` covers G10-PUB-01..08. Subagent static trace found no fail-open
defect (fail-closed default, `PublicGraphVisibility` sole owner, private-channel fallback, Sanctum
token revoke on logout).
Evidence: <`php artisan test --filter=Gate10PublicGraphBoundaryTest` output — NOT YET CAPTURED>

## CDC failure matrix (PLAN.md Task 11)
Scenarios A–E (Laravel dies post-commit; CDC consumer dies after XADD before ACK; Redis outage +
recovery from durable offset; poison CDC record -> DLQ; process restart). Design supports each
(durable Debezium offsets in Redis, XAUTOCLAIM lease recovery, at-least-once + downstream idempotency,
bounded-retry DLQ). Evidence: <live Docker runs — NOT YET CAPTURED>

## Test evidence
- backend `php artisan test`: <NOT YET CAPTURED>
- `php artisan test --filter=CdcOutboxStreamConsumerTest` (needs Redis): <NOT YET CAPTURED>
- `php artisan test --filter=Gate10NoPollingArchitectureTest` (filesystem only): <NOT YET CAPTURED>
- frontend `npm run test:unit`: <NOT YET CAPTURED>
- frontend `npm run build`: <NOT YET CAPTURED>
- `docker compose config`: <NOT YET CAPTURED>
- MySQL binlog variables: <NOT YET CAPTURED>
- Debezium running + offset persistence + CDC stream names: <NOT YET CAPTURED>
- raw/domain stream XLEN + group state; DLQ state: <NOT YET CAPTURED>

## Final verdict
NOT YET EVALUATED
