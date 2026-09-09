# Pre-Stage-6 Task 1/3 — `sensor_readings.reading_time` semantics: resolved

Prerequisite for PLAN.md Stage 6.0A ("Before publishing a UTC contract, run a DB/application
timezone probe and record how existing `sensor_readings.reading_time` values are interpreted").
This document originally froze classification **C (mixed/ambiguous)** for this column. Pre-Stage-6
Task 1 closed every GAP below with real evidence, implemented the normalization fix, and this
document now records the **resolved storage rule** in force from this commit forward.

**Gate verdict: reading-time semantics RESOLVED — single deterministic storage rule, caller PHP
type no longer selects semantics. Historical classification C is superseded (see "Resolution"
below).**

## Resolution (Pre-Stage-6 Task 1)

Real evidence gathered against the running `back` container (`docker compose exec -T back ...`,
not simulated):

- `config('app.timezone')` = `America/Bogota` (unchanged, `back/config/app.php`).
- MySQL `@@session.time_zone` = `@@global.time_zone` = `SYSTEM`; the container's system timezone
  is UTC, so `NOW()` == `UTC_TIMESTAMP()` in that session. This closes **GAP-1**: the session
  timezone is not pinned by `back/config/database.php` (still no `'timezone'` key), but it
  resolves to UTC in the actual deployment container, not an unknown/arbitrary value.
- `sensor_readings` had **0 rows** and `raw_sensor_events` had **0 rows** at the time of this
  probe. This closes **GAP-3**: there was no historical data to inspect, reconcile, or migrate.
  Per the task's decision table, an empty table is the **"Disposable local/test DB"** case — the
  correct action is to document the legacy ambiguity (below, preserved for the historical record)
  and ship the normalization fix directly, with **no data migration** and **no rewriting of rows**
  (there were none to rewrite). Nothing was truncated or recreated, because nothing needed to be.
- **GAP-0/GAP-2** are closed: `back/tests/Feature/ReadingTimeSemanticsTest.php` and the new
  `back/tests/Feature/RawReadingNormalizerTest.php` were executed against the real `back` +
  MySQL container (`docker compose exec -T back php artisan test --filter=...`), not sqlite, not
  simulated. All 10 tests pass.
- **GAP-4** is closed by construction: `RawReadingNormalizer::normalize()` feeds
  `SensorReadingService::createReading()` from `data_get($event->payload, 'timestamp') ??
  $event->received_at ?? now()` — a raw payload string OR a `received_at` `DateTimeInterface`.
  Since `SensorReadingService` is now the single normalization owner (below), this raw-ingestion
  caller no longer needs a bespoke pre-conversion; whatever it hands the service converges on the
  same rule as every other caller.

### The resolved rule

`SensorReadingService::createReading()` (`back/app/Services/Ingestion/SensorReadingService.php`)
now routes every `reading_time` input through a private `normalizeReadingTime()` method before
handing Eloquent a value, so the column always receives one unambiguous APP_TIMEZONE
(`America/Bogota`) wall-clock `"Y-m-d H:i:s"` string:

| Input | Rule |
|---|---|
| `null` | `Carbon::now(APP_TIMEZONE)` |
| `DateTimeInterface` (e.g. a UTC Carbon) | Same instant, converted to APP_TIMEZONE — **no longer** written as literal foreign-timezone digits |
| Legacy offsetless `"Y-m-d H:i:s"` | Interpreted explicitly IN APP_TIMEZONE (unchanged from before — this was already correct) |
| RFC3339 with `Z` or explicit offset | Parsed as the instant it specifies, then converted to APP_TIMEZONE |

Implementation detail: `Carbon::parse($string, $appTimezone)->setTimezone($appTimezone)` handles
both string rows in one call — PHP's `DateTime` parser ignores the supplied default-timezone
argument whenever the string itself carries a UTC offset/`Z`, and honors it otherwise, so the same
call is correct for both the legacy and RFC3339 branches.

**Proof (`back/tests/Feature/RawReadingNormalizerTest.php`, run against MySQL):** four different
input representations of the identical physical instant (`2026-09-09T15:00:00Z` ==
`2026-09-09 10:00:00` America/Bogota) — a `DateTimeInterface` fallback via `received_at`, a legacy
offsetless string, an RFC3339 `Z` string, and an RFC3339 explicit-offset string — all four now
store the identical raw DB value `'2026-09-09 10:00:00'` and the identical instant on read-back.
Before the fix, the `DateTimeInterface` and RFC3339-`Z` cases stored `'2026-09-09 15:00:00'`
instead (literal UTC digits, mislabeled Bogota on re-read) — this was the concrete, executed
demonstration of the original classification-C bug, not merely a source-derived prediction.

### Why no historical migration was performed

The task's decision table (`.superpowers/sdd/PRE_GATE6_UNLOCK_PLAN/task-1-brief.md`, step 1D)
requires a live migration/reconciliation plan only when a **non-disposable** DB has evidence of
mixed writers. This DB is disposable/local (0 rows in both `sensor_readings` and
`raw_sensor_events` at probe time), so that branch does not apply. No row was rewritten, and no
`Z` suffix was appended to any historical string — there were no historical strings to touch.

## Status of this document — historical context (superseded by "Resolution" above)

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

**This section is retained for the historical evidence trail only — see "Resolution" above for
the rule now in force.**

## Verified facts (read from source) — historical, as originally authored

*(The claims below describe the code BEFORE Pre-Stage-6 Task 1's fix. See "Resolution" above for
the rule now in force; the write-path bullet is corrected inline.)*

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

## The A/B/C classification and its evidence — historical (resolved, see above)

**C — mixed/ambiguous**, for two independent, source-verifiable reasons — this was the state
BEFORE Pre-Stage-6 Task 1's fix; both reasons below are eliminated by the resolved rule:

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

## New-ingestion input semantics — HTTP boundary validation (unchanged by this task)

`SensorApiController::store()`'s validator still only accepts the legacy offsetless format at the
HTTP boundary (`ReadingTimeSemanticsTest::test_rfc3339_utc_z_format_is_rejected_by_current_ingestion_validation`
and `::test_rfc3339_explicit_offset_format_is_rejected_by_current_ingestion_validation` still pass,
unchanged — this task did not touch controller-level validation, only `SensorReadingService`'s
internal normalization):

| Input | Accepted at HTTP boundary today? | Storage semantics once it reaches `SensorReadingService` |
|---|---|---|
| Legacy `Y-m-d H:i:s` (offsetless) | Yes — the only format `SensorApiController::store()`'s validator accepts | Interpreted explicitly in APP_TIMEZONE (Bogota) |
| RFC3339 `...Z` | **No** — fails `date_format:Y-m-d H:i:s` validation, HTTP 422 | n/a at HTTP boundary; if reached directly (e.g. raw-ingestion path), parsed as its instant and converted to APP_TIMEZONE |
| RFC3339 explicit offset (e.g. `...-05:00`) | **No** — same validation rule, same rejection | Same as above |
| Missing `reading_time` | Yes — field is `nullable` | `Carbon::now(APP_TIMEZONE)` |
| `DateTimeInterface` (e.g. raw-ingestion `received_at` fallback) | n/a — not an HTTP wire format | Same instant, converted to APP_TIMEZONE |

**Rule now in force (superseding the old "never pass a UTC DateTimeInterface..." warning):** any
caller of `SensorReadingService::createReading()` — HTTP controller, `RawReadingNormalizer`, or a
future raw-consumer/CDC processor — gets caller-type-independent, instant-preserving storage. The
`RawReadingNormalizer`'s `data_get($event->payload, 'timestamp') ?? $event->received_at ?? now()`
fallback chain (mixing a raw payload string and a `DateTimeInterface`) is safe as written, because
`SensorReadingService` now normalizes whichever branch it receives.

## GAPs — exact commands to close each one

**GAP-0 — Execute the written test suite at all.** CLOSED (Pre-Stage-6 Task 1). Run against the
real `back` + MySQL container:

```bash
docker compose exec -T back php artisan test --filter=ReadingTimeSemanticsTest
docker compose exec -T back php artisan test --filter=RawReadingNormalizerTest
```

Result: 10/10 pass.

**GAP-1 — MySQL session/global timezone facts** (Reason 2). CLOSED. Probed against the running
`back`/MySQL container: `@@session.time_zone` = `@@global.time_zone` = `SYSTEM`; the container
resolves `SYSTEM` to UTC, so `NOW()` == `UTC_TIMESTAMP()`. `config('app.timezone')` remains
`America/Bogota` — the two do differ, confirming Reason 2 was real, not just theoretical. This is
exactly why storage now goes through an explicit `normalizeReadingTime()` conversion rather than
relying on MySQL's own `TIMESTAMP` UTC conversion to paper over the gap.

**GAP-2 — Confirm the input-type asymmetry numerically.** CLOSED. Before the fix,
`ReadingTimeSemanticsTest::test_fixed_utc_instant_round_trip_through_the_real_ingestion_path`
proved the predicted asymmetry with real, executed values (raw DB `'2026-09-09 15:00:00'` for a
UTC-instant `DateTimeInterface` input vs. `'2026-09-09 10:00:00'` for the equivalent-instant
legacy string) — Reason 1 moved from "predicted" to "confirmed" by running the suite. After the
fix, both inputs now store `'2026-09-09 10:00:00'` — the asymmetry is eliminated, confirmed by the
same (now-updated) test.

**GAP-3 — Inspect representative historical rows.** CLOSED. `sensor_readings` had 0 rows and
`raw_sensor_events` had 0 rows at probe time — there was nothing to inspect, and the disposable-DB
branch of the task's decision table applies (see "Resolution" above). If this doc is revisited
against a deployment that has since accumulated historical rows, use:

```sql
SELECT id, sensor_id, reading_time, created_at FROM sensor_readings ORDER BY id DESC LIMIT 20;
```

Rows written **before** this fix may still show the pre-fix asymmetry (Bogota-string writes are
fine as-is; any `DateTimeInterface`-sourced writes would carry the 5-hour mislabeling this task
fixed going forward) — do not assume any pre-fix row's `reading_time` is UTC-consistent without
checking its origin.

**GAP-4 — future raw-consumer timestamp construction.** CLOSED by the fix itself:
`SensorReadingService::normalizeReadingTime()` now performs the `->setTimezone(APP_TIMEZONE)`
conversion internally for every `DateTimeInterface` input, so `RawReadingNormalizer` (or any
future raw-consumer/CDC processor) does not need to pre-convert before calling `createReading()`
— it already didn't, and that was previously a latent bug; now it is correct by construction.

## What this task does and does not do

**Done (Pre-Stage-6 Task 1):** implemented `SensorReadingService::normalizeReadingTime()` as the
single write-time normalization owner; proved it with `RawReadingNormalizerTest` (4 new cases) and
updated `ReadingTimeSemanticsTest` to assert the resolved (not the old ambiguous) behavior; closed
GAP-0 through GAP-4 with real evidence gathered against the running `back`/MySQL container.

**Not done (explicitly out of scope for this task):** no historical-row migration (none existed —
0 rows in both tables at probe time); no change to `SensorReading::$casts` or the `reading_time`
column type/schema; no change to `SensorApiController`'s HTTP-boundary validation rule (RFC3339
remains rejected at that layer); no change to `RawSensorEvent`'s own `received_at`/`processed_at`
casts, which carry the identical caller-type-dependent ambiguity this task fixed in
`SensorReadingService` but were not in this task's Modify list — a future task should apply the
same fix there if `RawSensorEvent`'s own timestamp fields are ever read for anything beyond
feeding `RawReadingNormalizer`'s already-normalized fallback chain.
