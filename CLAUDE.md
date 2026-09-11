# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project shape

Monorepo IoT platform, three independently runnable parts:

- `back/` — Laravel 12 API (PHP 8.2+), Sanctum auth, broadcasting, Eloquent observers/events, Redis.
- `front/` — Vue 3 + Vite + Pinia + Bootstrap 5 SPA, consumes the API, subscribes to realtime via Laravel Echo / Pusher-compatible transport.
- `ingestion_service/` — standalone Python service (MQTT/HTTP raw ingestion), independent venv, forwards raw events to the Laravel backend.
- `script_datos.py` (root) — Python simulator that POSTs synthetic sensor readings against the running backend, useful for local end-to-end testing.
- `memory/`, `docs/` — technical/compliance documentation (ICONTEC/ISO alignment docs live in `docs/`, formal write-ups at repo root as `DOCUMENTACION_PROYECTO.md` / `ANALISIS_PROYECTO.md`).

Current branch of active work is `refraccion` (ahead of `main`); `audit.md` at repo root (untracked, not committed) holds a full architecture audit of this branch — read it before proposing realtime/event-driven changes, it already has root causes, a polling inventory, and a task backlog (TASK-001..012).

## Commands

### Backend (`back/`)

```bash
cd back
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000

php artisan test                       # full suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=SensorReadingAlertTest   # single test class/method
php artisan route:list --path=api
php artisan optimize:clear             # clear config/cache/route caches after config changes
```

Isolated test run (SQLite in-memory, matches CI-like conditions, avoids false negatives from a real MySQL/Redis dev stack):

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array \
SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test
```

### Frontend (`front/`)

```bash
cd front
npm install
cp .env.example .env
npm run dev              # http://localhost:5173
npm run build
npm run preview

npm run test:structure   # scripts/verify-phase3.mjs — structural source checks
npm run test:phase4
npm run test:phase5
npm run test:phase7
```

These `test:*` scripts are structural/string checks against the source, not a browser test suite — there is no Playwright/e2e harness wired up yet (see "Known gaps" below).

### Docker (full local stack)

```bash
docker compose up -d       # back:8000, front:5173, db:3306 (MySQL 8), redis:6379
docker compose --profile queue up -d queue   # optional queue worker
curl http://localhost:8000/api/health
docker compose exec back php artisan migrate --force
```

### Ingestion service (`ingestion_service/`)

Has its own `.venv` and `requirements.txt`; run `pytest` inside it for `tests/test_{backend_client,normalizer,validators}.py`.

## Architecture

### Backend layering

`routes/api.php` → `Http/Controllers/Api/*` → `Services/*` → `Models` → `Observers` → `Events` → (broadcast + `Mail`). Business-rule side effects (alert evaluation, email) live in **Observers** (`SensorReadingObserver`, `AlertObserver`), not in controllers or views — when adding a new domain transition, follow that same observer/event split rather than inlining logic into a controller.

Two parallel ingestion paths currently exist and are not yet unified:
1. **Legacy/simple path**: `POST /api/sensors/{sensor}/readings` (IoT API-key auth) → `SensorApiController` → direct `SensorReading` persistence → `SensorReadingObserver` evaluates alert rules synchronously → `AlertObserver` broadcasts + sends email synchronously on creation.
2. **Raw ingestion path** (newer): `IngestionController` atomically persists `RawSensorEvent` and `RawEventOutbox`; `RawOutboxRelay` is the sole caller of `RawSensorEventPublisher`, which performs the Redis `XADD` to `iot.raw-events`. The `raw:consume` command runs the `raw-process-v1` consumer group, normalizes readings through `RawReadingNormalizer`, uses `raw_sensor_events.status` as the one-group idempotency ledger, reclaims idle pending deliveries, and sends terminal failures to the configured dead-letter stream.

Auth: Sanctum tokens with abilities (`*` for admin, `read` otherwise); IoT device traffic uses a separate `X-Device-Key`/`api_key` scheme, not Sanctum. Admin-only endpoints are gated by the `admin` middleware. Rate limits are named and differentiated: `api-read` (120/min), `api-write` (60/min), `auth-login` (5/min).

### Frontend realtime model

`front/src/realtime/{echo.js,useAlertsRealtime.js,useSensorRealtime.js}` wrap a single Laravel Echo/Pusher-JS connection (one Echo instance is meant to be reused, not one per component). Production Vue code no longer contains periodic polling timers that duplicate realtime state. Recovery is lifecycle-triggered (subscription/reconnect/visibility/auth lifecycle as applicable), and the current frontend tests validate those recovery paths. The test scripts are structural/source checks, not browser or end-to-end coverage.

The realtime modules own lifecycle-triggered recovery (snapshot/cursor handling as applicable); consult their source and tests when changing recovery behavior.

State ownership target (not yet implemented): WebSocket events → Pinia store (domain projection) → Vue components. Don't add a fourth parallel state path (component-local polling) when touching a screen that already has a store + realtime composable for the same domain.

### Known gaps (don't assume otherwise)

- No browser-based test suite (Playwright or similar) exists; `npm run test:phase*` are static/string checks only. Current frontend tests validate lifecycle-triggered recovery without periodic polling.
- Alert resolution and device status changes do not fully propagate through events yet (creation does; resolution/bulk-resolve and device status transitions are the known incomplete paths — `Eloquent::update()` bulk calls, e.g. `resolveAll()`, do not fire model observers).
- `database/seeders/SystemSettingsSeeder.php` has historically carried real-looking SMTP credentials — check before reusing seeder output, rotate rather than assume it's synthetic.
- `docs/api/openapi.yaml` / Postman collections can drift from actual routes; regenerate after route changes rather than trusting them blindly.

### Security surface already in place

Strict IoT payload validation (`value`, `reading_time`, `api_key`) with rejection + logging of unexpected fields; device `status`/`is_active` consistency check before ingesting; `User` model hardened against privilege escalation via mass assignment. See README.md's "Seguridad (estado real)" section for the full current list — don't re-implement checks that already exist there.
