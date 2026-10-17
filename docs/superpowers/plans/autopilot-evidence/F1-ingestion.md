# F1 evidence: lossless ingestion + broker auth

## Changes
- `backend_client.py`: `BackendClientError.retryable` (True: network/timeout/5xx/408/429; False: other 4xx, empty token).
- `spool.mark_failed(retryable=...)`: True never dead-letters, False dead-letters at once, None keeps legacy max_attempts. Worker passes `exc.retryable`. Backoff exponent capped (no huge ints after thousands of retries).
- `spool.replay_dead_letters(id|None)` + CLI `python -m app.replay_dead_letters [--all | --id X]` (INSERT OR IGNORE by source_event_id).
- `mqtt_client._on_message`: topic node != `payload.device.node_id` goes to quarantine (`node_mismatch`).
- Broker: `mosquitto.conf` (max_queued_messages 0, autosave_interval 60, log_type error/warning/notice, allow_anonymous false, password_file, acl_file), `acl`, `make-users.sh`, compose mounts config dir, `.gitignore` for passwd, `MQTT_HOST_PORT` override.
- Firmware `config.h`: `MQTT_USERNAME NODE_ID`, password placeholder. READMEs updated (lab_esp32 step 1 only, ingestion_service).
- Root `.env.example` NOT edited (owned by F2, has no MQTT vars); documented in `ingestion_service/README.md` instead.
- Test change: `test_worker_dead_letters_after_exhausting_retries` encoded the old bug (transient -> dead letter); replaced by `test_worker_dead_letters_permanent_error_immediately`.

## Tests
`cd ingestion_service && $VENV/bin/python -m pytest -q tests` -> 53 passed (baseline 49: +4 new in `tests/test_f1_lossless.py`, 1 test replaced). Note: `test_quarantine_snippet_is_capped_not_unbounded` takes ~100 s (pre-existing, regex).
Firmware: `arduino-cli compile --fqbn esp32:esp32:esp32 lab_esp32/firmware/do_sensor_node` -> OK (72% flash).

## Broker auth proof (project `f1-broker-test`, host port 18830, torn down with `down -v`)
```
anonymous pub                      -> Connection Refused: not authorised (rc=5)
ingestion sub 'iot/+/readings'     -> received: iot/lab_postgrado_nodo_01/readings own-ok
node pub own topic                 -> rc=0, delivered to ingestion
node pub iot/otro_nodo/readings    -> rc=0 (QoS1 ack) but NOT delivered (ACL drops silently)
ingestion pub iot/x/readings       -> rc=0 but NOT delivered
```
