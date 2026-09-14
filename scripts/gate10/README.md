# Gate 10 live-Docker fault matrix

`fault_injection.py` is an operator-run, stdlib-only integration harness for the final MySQL outbox -> Debezium -> Redis CDC -> application-stream -> raw-consumer path. It is intentionally not an app worker and does not add a polling or recovery owner.

It requires a controlled, disposable or otherwise approved Compose environment. Scenarios stop/restart `back`, `redis`, and `outbox-cdc-consumer`; do not aim it at a shared or production deployment.

## Required configuration

Set these without committing values:

```text
GATE10_INGESTION_TOKEN=<the configured INGESTION_SERVICE_TOKEN>
GATE10_NODE_ID=<existing devices.serial_number with mapped sensors>
GATE10_PAYLOAD_FILE=<path to a JSON payload template>
```

The payload file is the inner `payload` object accepted by `POST /api/ingestion/events`, not the complete request body. The harness inserts a unique `source=gate10-fault-harness`, `source_event_id`, UTC receipt time, and the configured `payload.device.node_id` for every scenario. It rejects templates with no numeric sensor values or `qc.valid=false`.

Example (do not treat sensor names as universal; they must map to the named device):

```json
{
  "device": {},
  "sensors": {
    "temperature": {"value": 21.5}
  }
}
```

Optional overrides are `GATE10_BACK_HOST` (default `127.0.0.1`), `GATE10_BACK_PORT` (default `8000`), `GATE10_DB_DATABASE`, `GATE10_DB_ROOT_PASSWORD`, `GATE10_COMPOSE_FILE`, `GATE10_COMPOSE_PROJECT`, `GATE10_TIMEOUT_SECONDS`, `GATE10_OBSERVATION_INTERVAL_SECONDS`, `GATE10_CLAIM_IDLE_MS` (default `0` for deterministic claim), and `GATE10_CDC_PAUSE_MS` (1,000--60,000; default `30,000`).

## Deferred operator procedure

Bring up the required topology using the existing deployment credentials, then run:

```text
docker compose --profile workers up -d db redis back debezium raw-consumer outbox-cdc-consumer
python scripts/gate10/fault_injection.py --scenario all
```

Before Docker preflight, the harness requires a clean **tracked** Git worktree (`git status --porcelain=v1 --untracked-files=no`). This prevents Compose bind mounts from executing modified source while labeling the result with the prior `HEAD` SHA. Untracked files do not affect this identity check. A dirty tracked tree, unavailable Docker, or unhealthy required service exits `2` with `BLOCKED: ENVIRONMENT` and writes no closure evidence.

For Scenario B and E it stops the normal CDC consumer, launches an isolated one-off consumer with `APP_ENV=local` and `GATE10_CDC_PAUSE_AFTER_PUBLISH_MS`, waits for its post-XADD/pre-mark/pre-XACK log checkpoint, kills it, and launches a fresh one-off consumer without the pause using `--claim-idle=0` by default. The production code ignores the pause environment variable outside Laravel `local`/`testing` environments.

## Evidence and pass criteria

Each scenario receives a distinct `source_event_id` (Scenario D carries it in the deliberately malformed CDC entry). The JSON result at:

```text
.audit-e2e/results/gate10-faults-<current-sha>.json
```

records before/after MySQL raw-event/outbox state, Redis `XLEN` and `XPENDING` observations, relevant DLQ payloads, and the count of readings for `GATE10_NODE_ID`. `docs/implementation/gate10-fault-matrix-current.md` is generated from that JSON.

`--scenario all` exits `0` only when A--E all record `PASS`. A failed assertion still records that scenario's before/after evidence and exits nonzero. The bounded observations in this harness are test evidence waits; they are not an application delivery loop or a substitute for CDC/consumer recovery.
