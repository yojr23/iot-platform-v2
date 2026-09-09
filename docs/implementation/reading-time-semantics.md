# Pre-Stage-6 Task 3 — `sensor_readings.reading_time` semantics freeze

Prerequisite for PLAN.md Stage 6.0A ("Before publishing a UTC contract, run a DB/application
timezone probe and record how existing `sensor_readings.reading_time` values are interpreted").
This document is the frozen answer that gates Stage 6.0B's UTC graph-range contract. It does not
implement that contract.

**Gate verdict: reading-time semantics frozen: C (Stage 6 blocked)**

## Status of this document — read before trusting the classification

The authoring session had **no `php` binary, no Docker, no MySQL client** reachable on `PATH`
(confirmed by direct probing, not assumed). `back/tests/Feature/ReadingTimeSemanticsTest.php` was
written TDD-style against the real write path and reviewed line-by-line against Laravel's
documented Eloquent attribute-casting mechanics, but **it has not been executed**, and the MySQL
session-timezone SQL in GAP-1 below has not been run against any real server. Classification C is
therefore a **source-derived, unexecuted-but-falsifiable** conclusion: it rests on two independent
structural facts (below) that do not themselves require runtime execution to establish "this
column's semantics depend on caller input type and unpinned DB config," but the exact numeric
values (what a real MySQL server does) are unverified. Close every GAP before removing the C
verdict.

## Verified facts (read from source)

- `config('app.timezone')` = `'America/Bogota'` (`back/config/app.php:67`) — PHP's
  `date_default_timezone_get()` is set to this at Laravel bootstrap, and Bogota has a fixed
  UTC-05:00 offset with no DST, so this fact alone doesn't introduce a variable offset.
- `sensor_readings.reading_time` is a Laravel `timestamp` column, `->useCurrent()`
  (`back/database/migrations/2025_04_29_134329_create_sensor_readings_table.php:18`). On MySQL,
  Laravel's `timestamp()` blueprint method maps to SQL `TIMESTAMP`, not `DATETIME` — a real
  distinction because MySQL's `TIMESTAMP` type converts values to UTC internally at write time and
  back to the *session* `time_zone` at read time; `DATETIME` does not. (On SQLite there is no such
  server-side type-level conversion at all — SQLite stores whatever string it's given.)
- `App\Models\SensorReading::$casts = ['reading_time' => 'datetime']`
  (`back/app/Models/SensorReading.php:16`).
- The real write path is `App\Services\Ingestion\SensorReadingService::createReading()`
  (`back/app/Services/Ingestion/SensorReadingService.php:18-24`), signature
  `(Sensor $sensor, float $value, DateTimeInterface|string|null $readingTime = null)`, called by
  both `SensorApiController::store()` (HTTP ingestion) and directly in tests. Missing input falls
  back to `now()`.
- The real HTTP ingestion validation (`SensorApiController::store()`,
  `back/app/Http/Controllers/Api/SensorApiController.php:63`) is
  `'reading_time' => 'nullable|date_format:Y-m-d H:i:s'` — **only** the legacy offsetless format is
  accepted at the HTTP boundary today. RFC3339 (`...Z` or explicit offset) fails validation (422)
  before it ever reaches `SensorReadingService`.
- `back/config/database.php`'s `mysql`/`mariadb` connections set no `'timezone'` key. Laravel only
  issues `SET time_zone = ?` on connect when that key is present. It is absent here, so this
  application **never pins the MySQL session timezone**; it is whatever the server/container
  default is (`SYSTEM` unless a MySQL image sets `TZ`/`--default-time-zone` explicitly — not
  verified, see GAP-1).

## The A/B/C classification and its evidence

**C — mixed/ambiguous**, for two independent, source-verifiable reasons:

### Reason 1 — input-type asymmetry inside `SensorReadingService::createReading()` (SQLite-provable, GAP-2 to confirm)

Laravel's Eloquent `datetime` cast does not treat every input the same way:

- A **raw string** (the legacy `Y-m-d H:i:s` HTTP path, and the `now()` fallback when
  `reading_time` is omitted) is parsed with `Carbon::createFromFormat(...)` using no explicit
  timezone argument, which defaults to `date_default_timezone_get()` == `America/Bogota`. The
  string is stored as literal digits and later re-read as Bogota wall clock — internally
  consistent, no drift, for callers that only ever use this path.
- A **`DateTimeInterface`/Carbon instance** (e.g. `CarbonImmutable::parse('...Z')`, a UTC
  instant) is wrapped via `Date::instance($value)`, which **preserves the instance's own
  timezone** rather than converting it to Bogota. Formatting that instance for storage
  (`'Y-m-d H:i:s'`) therefore writes the **UTC wall-clock digits**, with no offset marker. On
  read-back, those same digits are re-interpreted as Bogota wall clock (per the branch above) —
  silently shifting the represented instant by 5 hours from what the caller meant.

Concretely, `back/tests/Feature/ReadingTimeSemanticsTest.php::test_fixed_utc_instant_round_trip_through_the_real_ingestion_path`
predicts: input `2026-09-09T15:00:00Z` → raw DB value `'2026-09-09 15:00:00'` (UTC digits, not the
Bogota-converted `'2026-09-09 10:00:00'`) → cast value labeled `America/Bogota`,
`toIso8601String() == '2026-09-09T15:00:00-05:00'` → converting that back to UTC yields
`2026-09-09T20:00:00Z`, **not** the original `2026-09-09T15:00:00Z`. Same column, two different
effective semantics depending on which overload of the same method a caller uses — this is the
literal definition of "mixed" for this task's A/B/C rubric, and it is provable under SQLite alone
(pure PHP/Eloquent cast behavior, no MySQL-specific mechanism involved). **GAP-2: run the test
suite to convert this prediction into a confirmed result.**

### Reason 2 — unpinned MySQL `TIMESTAMP` session timezone (MySQL-only, GAP-1 to confirm)

Independent of Reason 1: because `reading_time` is SQL `TIMESTAMP` (not `DATETIME`) and this
application never sends `SET time_zone`, MySQL will interpret every inbound wall-clock string
using **its own session default**, not necessarily Bogota. If that session default differs from
`America/Bogota` (plausible default for an unconfigured Docker `mysql:8` container is UTC), then
even the "internally consistent" Bogota-string path from Reason 1 is *re*-interpreted a second
time by MySQL's `TIMESTAMP` conversion, on top of Reason 1's own ambiguity. This cannot be
confirmed or refuted from source alone — it requires a live MySQL connection.

Because neither reason can be fully closed without runtime evidence (GAP-1 and GAP-2), and Reason
1 alone already demonstrates non-uniform semantics by source inspection, the honest classification
today is **C**, not a provisional B pending confirmation. **Do not relabel historical rows as UTC
or as Bogota until both GAPs are closed against the real deployment target.**

## New-ingestion input semantics (frozen contract for today's code, not a target design)

| Input | Accepted at HTTP boundary today? | Effective interpretation if it reaches storage |
|---|---|---|
| Legacy `Y-m-d H:i:s` (offsetless) | Yes — the only format `SensorApiController::store()`'s validator accepts | Bogota wall clock (raw-string branch) |
| RFC3339 `...Z` | **No** — fails `date_format:Y-m-d H:i:s` validation, HTTP 422, no row written (`ReadingTimeSemanticsTest::test_rfc3339_utc_z_format_is_rejected_by_current_ingestion_validation`) | n/a — rejected before reaching `SensorReadingService` |
| RFC3339 explicit offset (e.g. `...-05:00`) | **No** — same validation rule, same rejection (`ReadingTimeSemanticsTest::test_rfc3339_explicit_offset_format_is_rejected_by_current_ingestion_validation`) | n/a — rejected |
| Missing `reading_time` | Yes — field is `nullable` | `now()` in `date_default_timezone_get()` == Bogota wall clock (same branch/semantics as the legacy string path) |

Only `SensorReadingService::createReading()`'s PHP signature accepts a `DateTimeInterface` (Reason
1's UTC-instant case); no current HTTP caller can reach that branch, because the controller-level
validator only lets offsetless strings through. That branch is reachable today only by a
non-HTTP/internal caller passing a `DateTimeInterface` directly (none currently do, per the earlier
`grep` inventory of `reading_time` call sites) — this makes Reason 1 a **latent** risk today, not
yet an observed production bug, but it is exactly the risk Stage 6.0B's "never relabel ambiguous
historical values as UTC" instruction is guarding against, and any future raw-consumer/CDC
processor (Stage 3/4) that constructs readings from a parsed-UTC device timestamp must go through
this exact method — so it will hit this branch the moment it exists.

**Rule going forward, until a migration/normalization decision is made:** never append `Z` to an
offsetless timestamp to fake UTC, and never pass a UTC `DateTimeInterface` into
`SensorReadingService::createReading()` expecting Bogota-consistent storage — today it is not.

## GAPs — exact commands to close each one

**GAP-0 — Execute the written test suite at all** (blocks GAP-2 below). Blocked in this session by
total absence of `php`/Docker/MySQL client on `PATH`. Run from `back/`:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array \
SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test --filter=ReadingTimeSemanticsTest
```

**GAP-1 — MySQL session/global timezone facts** (Reason 2). Not run; no MySQL reachable in this
session. Run against the real deployment target (e.g. via `docker compose exec back php artisan
diagnostics:reading-time-semantics`, which wraps this same query when the active connection driver
is `mysql`/`mariadb`, or directly):

```sql
SELECT @@session.time_zone, @@global.time_zone, NOW(), UTC_TIMESTAMP();
```

Compare `@@session.time_zone` against `config('app.timezone')` (`America/Bogota`). If they differ
(including `SYSTEM` resolving to something other than Bogota), Reason 2 is confirmed active, not
just theoretical.

**GAP-2 — Confirm the input-type asymmetry numerically.** Covered by GAP-0's test run;
`ReadingTimeSemanticsTest::test_fixed_utc_instant_round_trip_through_the_real_ingestion_path` and
`::test_legacy_offsetless_string_is_interpreted_as_bogota_wall_clock` must both pass with the exact
raw/cast values asserted in that file for Reason 1 to move from "predicted" to "confirmed."

**GAP-3 — Inspect representative historical rows** (task step 3; no local DB reachable, so no rows
were inspected and none were mutated). Read-only query, run once a DB is reachable (or via `php
artisan diagnostics:reading-time-semantics --limit=20`, added by this task for exactly this
purpose):

```sql
SELECT id, sensor_id, reading_time, created_at FROM sensor_readings ORDER BY id DESC LIMIT 20;
```

Cross-reference against the original ingestion source (device/MQTT log) if available for a sample
of rows, to see whether historical values line up with Reason 1's Bogota-string branch (the only
branch reachable via the HTTP path historically) or show any UTC-digit anomalies consistent with a
non-HTTP writer having used the `DateTimeInterface` branch.

**GAP-4 — `--interval=2` transitional relay / future raw-consumer timestamp construction.** Not a
runtime GAP, a design GAP: whichever component eventually parses a device-supplied UTC timestamp
out of `raw_sensor_events` and calls `SensorReadingService::createReading()` (Stage 3/4) must not
pass a UTC `DateTimeInterface` naively — per Reason 1, doing so does not store a UTC-consistent
value today. This needs either (a) an explicit `->setTimezone('America/Bogota')` conversion before
calling `createReading()`, keeping today's Bogota-wall-clock column semantics, or (b) a schema/cast
change to make the column unambiguous, decided together with Stage 6.0B, not implemented here.

## What this task does not do

This task freezes the observed rule and inventories the gaps; it does not migrate historical rows,
does not change `SensorReading::$casts`, does not change the `reading_time` column type, and does
not change `SensorApiController`'s validation rule. Those are Stage 6.0A/6.0B decisions once GAP-0
through GAP-3 are closed with real evidence.
