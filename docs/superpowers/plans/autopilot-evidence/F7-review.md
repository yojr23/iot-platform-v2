# F7 review (refraccion..HEAD, firmware excluded)

| Sev | Where | Issue | Status |
|---|---|---|---|
| Medium | ingestion_service/app/backend_client.py (retryable) | 401/403/404 and empty token were non-retryable: a rotated/misconfigured token or mid-deploy 404 dead-lettered every event (recoverable only by manual replay). | FIXED ec5b50c (now retried; test updated) |
| Medium | docker-compose.yml INGESTION_RAW_EVENTS_MIN_AGE_MS / DOMAIN_EVENTS_MIN_AGE_MS default 259200000 (3 d) | rawXadd uses MINID trimming; if raw-consumer is down >3 days (long weekend) unconsumed stream entries get trimmed. Raw rows remain in MySQL as received, but there is no automatic re-publish. | NOT FIXED: tradeoff vs 512 MB noeviction cap; consider 7 d or a replay command. |
| Low | docker-compose.yml LOG_STACK=${LOG_STACK:-daily} | Overrides back/.env (e.g. LOG_STACK=json) with daily in back/queue/worker/scheduler. Intended, but silently drops json output. | NOT FIXED (documented intent). |
| Low | TierStorage::move | ARCHIVE engine is non-transactional: if the DELETE fails after INSERT, rollback leaves rows in archive and hot, next run re-inserts (duplicates in archive, no loss). | NOT FIXED (no loss). |
| Low | TierStorage raw move | FK reading_projections.raw_sensor_event_id is nullOnDelete: archiving raw rows nulls the link in projections (id still exists in archive); raw_event_outboxes cascade-deleted (Debezium delete ops are ignored by CdcOutboxStreamConsumer, verified). Also unique(source, source_event_id) no longer protects replays older than RAW_HOT_DAYS. | NOT FIXED (accepted). |
| Low | preflight.sh | MQTT password passed with mosquitto_pub -P (visible in ps locally); never printed. | NOT FIXED |
| Low | CapacityCheck | Cache::add set before mail send; failed mail suppresses retry 6 h (logged). | NOT FIXED |

Verified OK: archive column lists match hot tables (raw: incl. source_event_id; audit: all cols; only those two migrations touch them); status filter processed only; cutoffs use now() in app tz, same as stored timestamps; TrimCdcStreams skips missing group/stream (XINFO error caught), never trims past last-delivered-id or min pending; ArchiveLogs skips audit*, today's file and files modified <24 h, .gz not matched by Monolog maxFiles; Alert::applyMailSettings is a verbatim extraction (null recipient returns before config changes); no secrets in diff; ACL pattern semantics fine (nodes write only own topic, ingestion read-only, nodes cannot read); CORS/Sanctum/Reverb defaults unchanged when env unset; migration down()/sqlite guard OK; noeviction: OOM makes XADD fail, outbox rows in MySQL are retried.

## Over-engineering (ponytail)
- CapacityCheck: configurable --warn/--critical and 3 metrics is fine; no cuts.
- TierStorage: closures + dry-run + batch loop slightly heavy but justified. Lean enough.
- replay_dead_letters CLI + spool method: needed for lossless story. Net: -0 lines possible.

## Tests
- backend: no FAIL lines (summary line swallowed by warnings: "6 passed", 561 warnings, 17029 assertions)
- ingestion: 53 passed
- docker compose config -q: OK
