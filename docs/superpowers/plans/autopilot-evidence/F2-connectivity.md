# F2 evidence: conexion al laboratorio

## Changes
- `docker-compose.yml`: `back` CORS_ALLOWED_ORIGINS / SANCTUM_STATEFUL_DOMAINS now `${VAR:-<old value>}`;
  `reverb` gets REVERB_ALLOWED_ORIGINS (default `http://127.0.0.1:4173,http://localhost:4173`, the same
  default `config/reverb.php` already used; matched by HOST only); `front` VITE_API_BASE_URL overridable.
- `.env.example`, `front/.env.example`: LAN block (commented, defaults unchanged).
- `RawReadingNormalizer::normalize()`: `forceFill(['last_communication'=>now()])->saveQuietly()` once the
  device is resolved. Chosen over the event: the event has no dispatcher and its listener `update()`s
  (fires observers). QC-invalid receipts return earlier and do not touch it (no device resolved).
- `lab_esp32/preflight.sh` (+ `--e2e`, `COMPOSE_PROJECT`, `PREFLIGHT_MQTT_HOST`, MQTT_USERNAME/PASSWORD).
- `lab_esp32/README.md`: step 5 Preflight (old 5 -> 6) and "Portal desde otros PCs del lab (LAN)".

## Commands / results
- `docker compose config -q` -> OK (defaults).
- `CORS_ALLOWED_ORIGINS=http://192.168.1.20:5173 REVERB_ALLOWED_ORIGINS=192.168.1.20 docker compose config | grep -E "CORS|REVERB_ALLOWED"` -> both overridden.
- `bash -n lab_esp32/preflight.sh` -> OK. shellcheck not installed. Dry run with no stack: runs to the
  end, 14 FAIL / 3 PASS (.env vars), exit 1, no values printed. Real run is F6.
- `php artisan test --filter=RawReadingNormalizerTest` -> new test passes (17 tests, 0 failures).
- Full suite (isolated sqlite env): 0 failures (F3's new tests included at the time of the run).

## Risks found (real .env files not edited)
- `front/.env` has VITE_PUSHER_FORCE_TLS=true, empty HOST, scheme https: breaks realtime if used by `npm run dev`
  outside Docker. Under Docker the compose env wins.
- Root `.env` has `VITE_PUSHER_PORT=6001` but Reverb is published on 8080 -> realtime in the browser
  would target 6001. Set 8080 (or remove) before the lab.
