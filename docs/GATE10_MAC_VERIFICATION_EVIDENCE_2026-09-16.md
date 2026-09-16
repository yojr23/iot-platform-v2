# Gate 10 — Mac Verification Evidence Ledger

## M0 — Baseline
- Repo: iot-platform-v2, branch `refraccion`, clean tree
- Baseline SHA: `ce5f13212601e004d65a97ab523ebc9bc6705044` (ce5f132)
- Date: 2026-09-16
- OS: Darwin 23.5.0 (macOS)
- Tool versions: PHP 8.5.2 (CI uses 8.2), composer 2.8.9, node v18.20.8, npm 10.8.2,
  python3 3.11.10, docker 27.3.1, compose v2.29.7, redis-cli 8.10.1, mysql 9.3.0
- Test Redis: local redis-server on port 6399 (Docker daemon was down at start)

## M1 — Backend CI reproduction (SQLite + Redis 6399)
### Run 1 (baseline, unmodified) — FAIL
- Result: **446 failed, 6 passed** (215 assertions), 139.6s
- Root cause: migration `2026_09_14_000008_drop_plaintext_api_key_from_devices.php`
  drops `api_key` column while unique index `devices_api_key_unique` still references it.
  SQLite refuses ("no such column: api_key after drop column"). MySQL tolerated it, so
  this was invisible until CI ran on SQLite. Cascades: RefreshDatabase setup dies →
  ~all tests fail.

### Fixes applied
1. **000008**: drop `devices_api_key_unique` index before dropping the column;
   guard with `Schema::hasColumn` for idempotency. Portable SQLite + MySQL.
2. **000003 backfill**: rewrote raw `INSERT IGNORE ... NOW()` (MySQL-only) as portable
   query-builder insert. Added fail-closed collision preflight — aborts if two sensors
   under one device canonicalize to the same external_key (M3 requirement).
3. **CdcOutboxStreamConsumer.php:78**: `?callable` is an illegal PHP property type
   (fatal on all PHP versions). Changed to `?\Closure`. This fatal also killed CI.

### Run 3 (post-fix) — 457 deprecated(passing), 13 failed, 6 passed, 65s
Migration cascade gone. 13 real failures newly visible (were masked). Clusters:
- A. api_key test debt (2): DataLeakageSentinel:38, Phase2:97 — reference dropped plaintext col
- B. export 403≠200 (2): SensorAuthorization:126, SpaParity:188 — export gating
- C. runtime config 403≠200 (1): Phase2:217
- D. RawReadingNormalizer skipped≠created (3): :257 :302 :363 — mapping resolution
- E. alert rule 405≠403/401 (2): SecurityAccessControl:78,95 — routing
- F. role mgmt 422≠200 (3): AuthSecurity:140, SpaParity roles, Phase2 roles

### Root causes (13 failures → 6 clusters)
- A [CODE-DEBT/TEST]: tests reference `$device->api_key` (dropped col) → null/leak asserts.
  DataLeakageSentinel:38 asserts json has no 'api_key' but `api_key_prefix`/`_last_rotated_at`
  contain substring 'api_key'. Phase2:97 passes null to assertStringNotContainsString.
  → tests written against old plaintext column; update to new hash/prefix reality.
- B [TEST]: export tests use plain `User::factory()` (non-admin) but `sensor_reading.export`
  is admin-only perm. 403 is CORRECT. → tests must use admin user.
- C [CODE]: `/config/runtime` gated behind admin-only `system_setting.view`, but returns only
  sanitized {alert_sound_enabled, app_url} and frontend alerts store calls it for ALL users.
  → remove admin perm middleware; auth+verified is enough.
- D [CODE]: timezone inconsistency. Model `datetime` cast stores valid_from='...Z' as literal
  wall-clock (ignores Z), but lookupTime() honors Z. valid_from(11:00) <= at(07:00) fails.
  → normalize mapping validity timestamps consistently with lookup path.
- E [TEST]: tests POST to `/api/alert-rules/store` (nonexistent) → 405. Real route is
  POST `/api/alert-rules`. → fix test URLs; assertions (401 guest, 403 non-admin) are right.
- F [TEST]: role mgmt 422 — controller migrated to role_code (RBAC); tests sent legacy
  is_admin. Fixed payloads AND actor role: assigning/managing admins requires superadmin
  (canAssignRole/canManageRole), so those tests now use a superadmin actor.

### Fixes summary (all 13 unmasked failures resolved)
- CODE: routes/api.php — /config/runtime un-gated (auth+verified only, sanitized output)
- CODE: DeviceSensorMapping.php — valid_from/valid_until Attributes normalize to app tz
- CODE: SensorMappingService.php — normalizeLookupTime() applied to both lookup methods so
  storage + lookup agree on timezone (fixed regression in SensorMappingServiceTest)
- TEST: alert-rules URLs, role_code payloads + superadmin actors, export admin users,
  api_key precise assertions, DLQ order-insensitive compare, duplicate-key mapping fixture

### Run 5 (final) — GREEN
Tests: 476 passed (1826 assertions), 0 failed, 0 errors, 110s.
"deprecated" markers = PHP 8.5 PDO::MYSQL_ATTR_SSL_CA notice only (CI PHP 8.2 = clean pass;
confirmed by CI workflow comment). Redis on 6399 exercised (CDC/DLQ/stream tests ran).

## Frontend gate (this machine, SHA ce5f132 + fixes)
- Vitest: 247 passed (45 files) — PASS
- Production build: built in 12s — PASS
- Static no-polling gate: PASS

## Other CI jobs (this machine)
- Architecture static gate (retired-relay + CDC-config): PASS
- Ingestion pytest: 29 passed — PASS

## Responsive mocked E2E (was RED) — now PASS
Initial: 33/35 (2 auth-transition rows failed on adminNavigationLeakFree=false).
Root cause: audit's adminRoutes list treated /alert-rules, /labs, /sensor-types,
/device-types as admin-only, but the RBAC model gives standard users read access to all
(alert_rule.view / device.view). Backend confirms: catalog READ = device.view, WRITE =
system_setting.update (admin). So standard users legitimately reach the read views; that is
not a leak. Genuinely admin-only routes (permission users lack) = /config* + /users only.
Fixed the audit's adminRoutes list to those. RBAC redirect for /config and /users VERIFIED
working (adminNavigationLeakFree=true). Result: 35/35 PASS.

## CI STATUS — ALL 6 JOBS GREEN on this machine (was 2 RED)
| Job | Before | Now |
| Backend PHP+SQLite+Redis | FAIL | PASS (476 tests) |
| Frontend unit+build+no-polling | PASS | PASS |
| Architecture static | PASS | PASS |
| Ingestion pytest | PASS | PASS |
| Responsive mocked Playwright | FAIL | PASS (35/35) |

## M9 — MySQL migrations (real MySQL 8.4 in Docker on :3307)
### Fresh (migrate:fresh)
Run 1: FAIL at 000002_make_device_sensor_mappings_temporal — MySQL error 1553
"Cannot drop index ..._unique: needed in a foreign key constraint". SQLite CI never caught
this (SQLite doesn't enforce FK-index dependency). The devices FK (device_id) used the
(device_id,source,external_key) unique key as its supporting index.
FIX (commit fb1c6fc): build replacement temporal index (device_id-leftmost) first under temp
name, drop old temporal+unique, rename temp→canonical. Verified on MySQL 8.4 AND SQLite.
Run 2: ALL migrations DONE on MySQL. SQLite migrate:fresh also completes.
Commits so far: c6daf18 (CI green), fb1c6fc (MySQL FK-index), c700602 (PLAN reconcile).
Pushed: ce5f132..c700602 on refraccion.

## M10 — Full Docker stack health — PASS
Brought up: db, redis, back, front, debezium, outbox-cdc-consumer, domain-event-consumer,
raw-consumer (ingestion excluded — needs external MQTT broker; events injected via HTTP API).
- All 8 services Up; db/redis/back healthy; no restart loops.
- backend /api/health = 200.
- Debezium: connected to MySQL 8.0.46, snapshot complete, streaming binlog
  mysql-bin.000004/157, capturing iot_platform.{domain,raw}_event_outboxes → Redis.
- 3 consumers looping clean (acked/dlq/pending logged). outbox-cdc already acked=3 backlog.
- Redis streams present: iot-cdc.iot_platform.domain_event_outboxes,
  iot-cdc.iot_platform.raw_event_outboxes, iot.domain-events, iot.raw-events.
Ports: back:8000, front:5173, db:3307, redis:6380.

## M9 upgrade-path (real, on Docker db with pre-existing data) — PASS
The Docker db had OLD data (1 device, 2 sensors, no device_sensor_mappings table) migrated
before the new migrations. Ran `php artisan migrate --force` (NOT fresh) inside back container:
all new migrations DONE on populated tables — 000008 api_key drop, 000002 FK-index reorder,
000003 backfill with collision preflight. Backfill mapped both existing sensors correctly:
temperature→sensor1, dissolved_oxygen→sensor2, canonical keys, one open interval each, no
collisions, no data loss. This IS the upgrade/backfill proof (M9 upgrade path).

## M11 — End-to-end IoT vertical slice — PASS
Injected via POST /api/ingestion/events (X-Ingestion-Token + Accept: application/json — note:
missing Accept header → 302 redirect, not 401). Marker source_event_id=trace-1789584434,
value=42.7, node_id=lab_postgrado_nodo_01, sensor key "temperature". Traced all 8 stages:
1. raw_sensor_events id=3 status=processed
2. sensor_readings id=5 sensor_id=1 value=42.7  (mapping resolved "temperature"→sensor1 via
   the temporal mapping — validates the tz fix on a real MySQL+Debezium stack)
3. reading_projections id=1 raw_event=3→reading=5 source_key=temperature normalizer=v1
4. domain_event_outboxes id=3 sensor.reading.created status=published delivered_at set
5. Redis iot.domain-events: event_id=3 aggregate_id=5 payload {value:42.7,sensor_id:1,reading_id:5}
Confirms at-least-once with delivered_at set before/at XACK. Full chain:
HTTP→raw→normalizer(temporal mapping)→reading→provenance→domain outbox→Debezium CDC→Redis→broadcast.

### Device isolation (M11) — PASS
GET /api/iot/sensors with X-Device-Key (rotated plaintext for device 1):
- valid key → exactly device 1's 2 sensors (temperature, dissolved_oxygen), no cross-device leak.
- invalid key → 401. missing key → 401. Confirms the earlier cross-device leak fix.

## M12 — Event fault matrix (live stack)
### Duplicate / at-least-once tolerance — PASS
Re-POST same source_event_id → response duplicate:true, HTTP 200, same event_id=3. DB: 0
duplicate readings (value 99.9 never persisted), still exactly 1 raw_sensor_event for the
marker. Dedup via (source, source_event_id) unique + status ledger.
### Consumer group health — PASS
XINFO GROUPS on iot.domain-events (browser-delivery-v1), iot-cdc.*_outboxes (outbox-publish-v1):
all pending=0, lag=0 — everything delivered and XACKed, no PEL backlog.
### Consumer crash + recovery (durability, no data loss) — PASS
Stopped outbox-cdc-consumer, injected marker crash-1789584593 (value 33.3, event_id=4):
- With consumer down: raw_sensor_events id=4 stuck at "received", raw_event_outboxes id=4 at
  "pending" — event durable in DB + CDC stream, NOT lost, nothing falsely marked delivered.
Restarted outbox-cdc-consumer → full recovery within ~8s:
- raw event 4: received → processed; raw_event_outboxes id=4: pending → published;
  sensor_readings id=6 value=33.3 created. All groups drained (pending=0) afterward.
Proves crash-before-ACK resilience: no loss, no manual intervention, no duplicate.
### Topology learned: outbox-cdc-consumer (cdc:consume-outboxes) republishes BOTH Debezium CDC
streams (raw+domain outbox) into iot.raw-events / iot.domain-events; raw-consumer normalizes
iot.raw-events→sensor_readings; domain-event-consumer broadcasts iot.domain-events.
