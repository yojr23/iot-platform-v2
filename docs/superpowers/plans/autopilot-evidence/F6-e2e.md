# F6 evidence: live end-to-end simulation (2026-10-17)

Isolated stacks: `docker compose -p iot-e2e` (host ports back 8100, front 5273, mysql 3317, redis 6390,
reverb 8180; 8080 was held by another project's reverb) and broker `-p iot-e2e-broker` on host port 18831.
Config via a temp env file (copy of root `.env` + overrides); real `.env` files untouched. Both projects
torn down with `down -v`, generated `passwd` and all e2e passwords deleted.

| # | Scenario | Result | Notes |
|---|---|---|---|
| 1 | migrate + seeder + `preflight.sh` and `--e2e` | PASS | 17/17 PASS, then e2e reading reached `sensor_readings` (first try, no fixes) |
| 2 | firmware-shaped publish -> row, last_communication, public API, Reverb | PASS | value/reading_time exact (UTC payload stored in app TZ), `/api/public/graph/.../series` returns points, `NewSensorReading` received on `sensor.1` via pusher-protocol WS |
| 3 | Backend down, 30 readings, `INGESTION_RETRY_MAX_SECONDS=2` | PASS | attempts reached 37 (old code dead-letters at 20); spool 30 pending / 0 dead_letters; after `start back`: 30/30 in `sensor_readings`, pending 0, dead_letters 0 |
| 4 | Ingestion down, 20 readings, start | PASS | 0 rows while down, 20/20 (distinct) after start (persistent session queued them) |
| 5 | Duplicate payload twice | PASS | exactly 1 `sensor_readings` row |
| 6 | Real MySQL engines + `iot:storage:tier` | PASS | ARCHIVE on `*_archive`, Compressed on `sensor_readings`/`reading_projections`; 25 aged raw events moved (dry-run said 25, real 25, rerun 0); readings 58 before and after; raw_event_outboxes cascaded (58 -> 33) |
| 7 | capacity:check, logs:archive, cdc:trim, schedule:list, scheduler | PASS (1 fix) | see fix below; scheduler container Up, 5 jobs listed |
| 8 | Security | PASS | anonymous pub rc=5 refused; node -> other node's topic: ACL drops, 0 rows; spoofed payload node_id on own topic: quarantined (quarantine=1), 0 rows; POST /api/ingestion/events no token 401, bad token 401 |
| 9 | Portal | PASS (HTTP only) | front 200 on 5173 (5273 here), `/api/health` through the Vite proxy 200; no browser check done |

## Fix
- `68a0da6` fix(ops): `docker-compose.yml` now sets `LOG_STACK=${LOG_STACK:-daily}` and
  `LOG_LEVEL=${LARAVEL_LOG_LEVEL:-info}` on back, reverb, queue and the worker anchor. Found: the real
  `back/.env` has `LOG_STACK=single`, `LOG_LEVEL=debug`, so Laravel kept writing one unbounded `laravel.log`
  (14 MB already) and `iot:logs:archive` had nothing to rotate. Verified: config resolves to stack=daily, level=info,
  and `laravel-2026-10-17.log` is created. (`LARAVEL_LOG_LEVEL` is a separate name so the ingestion service's
  `LOG_LEVEL=INFO` cannot leak into Monolog.)

## Lab-day actions (user)
1. Root `.env`: `VITE_PUSHER_PORT=6001` is wrong, Reverb publishes 8080. Set `8080` (or delete the line).
2. Root `.env`: `VITE_PUSHER_APP_KEY=local-key` does not match Reverb's key (`REVERB_APP_KEY`, default `iotlocalkey`).
   Verified: key `local-key` -> `pusher:error 4001 Application does not exist`. Set it to `iotlocalkey` or delete the line.
   Also `VITE_PUSHER_APP_CLUSTER=mt1` is harmless.
3. Seeded DO sensor has `public_monitoring_enabled=0`: the anonymous graph (`/api/public/graph/bootstrap`) is empty until
   enabled in the admin UI (or log in to see it). Left as is on purpose (public exposure is a decision).
4. Broker credentials: `./make-users.sh lab_postgrado_nodo_01 ingestion`; put the `ingestion` password in root `.env` as
   `MQTT_USERNAME=ingestion` / `MQTT_PASSWORD=...` (the current `.env` has no MQTT credentials; the broker rejects anonymous).
   Node password goes in firmware `config.h`. `MQTT_HOST` must be the broker host reachable from the containers
   (`host.docker.internal` if the broker runs on the same PC).
5. Ports: another project's reverb currently holds 8080 and the `iotplatformv2-cert-*` containers are restart-looping; stop
   them before the lab or the default stack cannot bind 8080/8000.
6. LAN access from other PCs: `REVERB_ALLOWED_ORIGINS` is matched by host; a browser on `localhost` works by default
   (verified Origin `http://localhost:4173`), other PCs need the LAN vars from README.
7. Old `back/storage/logs/laravel.log` (14 MB, gitignored) stays; delete or gzip it by hand.

## Notes
- MySQL `NOW()` is UTC while Laravel stores app-timezone (America/Bogota, -5h) times: `iot:storage:tier` ages are off by 5 h
  (irrelevant for 14/90-day windows; matters only when aging test rows).
- `iot:capacity:check` reported disk CRITICAL (97 %): the dev Mac disk is really that full.
- Reverb check used a one-off node `ws` client inside the front container (key `iotlocalkey`, Origin localhost).

## Key outputs (trimmed)
```
preflight: PASS x17 ... PREFLIGHT OK ; --e2e: PASS e2e: lectura sintetica llego a sensor_readings
series: points [{"timestamp":"2026-10-17T05:38:37Z","value":8.101,"reading_id":2},...] stats count 4
WS: App\Events\NewSensorReading {"id":7,"sensor_id":1,"value":7.77,"reading_time":"2026-10-17T05:40:07Z",...}
S3 (backend down): pending_events 30, dead_letters 0, attempt=37 delay=2s ; after start: pending 0, dead 0, 30 rows
S4: before start 0 rows -> after 20 rows (20 distinct)
S5: dup_rows 1
S6: tier --dry-run 25 ; run 25 ; rerun 0 ; raw 58->33, archive 0->25, readings 58->58
S7: capacity table (disk/mysql_schema/redis_memory) ; cdc:trim "trimmed 0" x2 ; logs --compress compressed=2 ; --purge purged=2 (aged .gz and fake 40-day files)
S8: anon -> "Connection Refused: not authorised" rc=5 ; no token 401 ; bad token 401
```
