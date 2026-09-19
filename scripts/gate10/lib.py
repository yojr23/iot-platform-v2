"""Stdlib-only primitives for the Gate 10 live-Docker fault matrix.

This module intentionally has no application-side recovery logic. It only drives the
existing Compose topology and captures evidence about its durable boundaries.
"""

from __future__ import annotations

import copy
import dataclasses
import datetime as dt
import json
import os
import pathlib
import shlex
import subprocess
import time
import uuid
from typing import Any, Callable, Iterable, Mapping
from urllib import error, request


class Gate10Error(RuntimeError):
    """A failed precondition or evidence assertion in the fault matrix."""


@dataclasses.dataclass(frozen=True)
class CommandResult:
    args: tuple[str, ...]
    stdout: str
    stderr: str


class CommandRunner:
    """Runs fixed argument vectors; no shell is used for credentials or payload data."""

    def run(self, args: Iterable[str], *, timeout: int = 60, check: bool = True) -> CommandResult:
        argv = tuple(str(argument) for argument in args)
        try:
            completed = subprocess.run(
                argv,
                check=False,
                capture_output=True,
                text=True,
                timeout=timeout,
            )
        except FileNotFoundError as exc:
            raise Gate10Error(f"required executable is unavailable: {argv[0]}") from exc
        except subprocess.TimeoutExpired as exc:
            raise Gate10Error(f"command timed out after {timeout}s: {shlex.join(argv)}") from exc

        result = CommandResult(argv, completed.stdout.strip(), completed.stderr.strip())
        if check and completed.returncode != 0:
            detail = result.stderr or result.stdout or f"exit {completed.returncode}"
            raise Gate10Error(f"command failed: {shlex.join(argv)}: {detail}")
        return result


def require(value: bool, message: str) -> None:
    if not value:
        raise Gate10Error(message)


def sql_literal(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def utc_now() -> str:
    return dt.datetime.now(tz=dt.timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def parse_json(value: str, description: str) -> Any:
    try:
        return json.loads(value)
    except json.JSONDecodeError as exc:
        raise Gate10Error(f"{description} did not return JSON: {value[:500]}") from exc


def flat_fields(raw_fields: Any) -> dict[str, str]:
    """Normalise RESP2/RESP3 JSON field representations from redis-cli."""
    if isinstance(raw_fields, dict):
        return {str(key): str(value) for key, value in raw_fields.items()}
    if not isinstance(raw_fields, list):
        return {}
    return {
        str(raw_fields[index]): str(raw_fields[index + 1])
        for index in range(0, len(raw_fields) - 1, 2)
    }


@dataclasses.dataclass(frozen=True)
class HarnessConfig:
    root: pathlib.Path
    ingestion_token: str
    node_id: str
    payload_template: dict[str, Any]
    backend_host: str
    backend_port: int
    database: str
    db_root_password: str
    compose_file: pathlib.Path
    compose_project: str | None
    timeout_seconds: int
    observation_interval_seconds: float
    claim_idle_ms: int
    fault_pause_ms: int

    @classmethod
    def from_environment(cls, root: pathlib.Path) -> "HarnessConfig":
        def required(name: str) -> str:
            value = os.environ.get(name, "")
            require(bool(value), f"{name} is required")
            return value

        payload_file = pathlib.Path(required("GATE10_PAYLOAD_FILE")).expanduser()
        if not payload_file.is_absolute():
            payload_file = root / payload_file
        require(payload_file.is_file(), f"GATE10_PAYLOAD_FILE does not exist: {payload_file}")
        payload = parse_json(payload_file.read_text(encoding="utf-8"), "GATE10_PAYLOAD_FILE")
        require(isinstance(payload, dict), "GATE10_PAYLOAD_FILE must contain a JSON object")

        def integer(name: str, default: str) -> int:
            try:
                return int(os.environ.get(name, default))
            except ValueError as exc:
                raise Gate10Error(f"{name} must be an integer") from exc

        def decimal(name: str, default: str) -> float:
            try:
                return float(os.environ.get(name, default))
            except ValueError as exc:
                raise Gate10Error(f"{name} must be a number") from exc

        timeout_seconds = integer("GATE10_TIMEOUT_SECONDS", "120")
        observation_interval_seconds = decimal("GATE10_OBSERVATION_INTERVAL_SECONDS", "1")
        claim_idle_ms = integer("GATE10_CLAIM_IDLE_MS", "0")
        fault_pause_ms = integer("GATE10_CDC_PAUSE_MS", "30000")
        require(timeout_seconds > 0, "GATE10_TIMEOUT_SECONDS must be positive")
        require(observation_interval_seconds > 0, "GATE10_OBSERVATION_INTERVAL_SECONDS must be positive")
        require(claim_idle_ms >= 0, "GATE10_CLAIM_IDLE_MS must be non-negative")
        require(1000 <= fault_pause_ms <= 60000, "GATE10_CDC_PAUSE_MS must be between 1000 and 60000")

        compose_file = pathlib.Path(os.environ.get("GATE10_COMPOSE_FILE", "docker-compose.yml"))
        if not compose_file.is_absolute():
            compose_file = root / compose_file
        require(compose_file.is_file(), f"Compose file does not exist: {compose_file}")
        backend_port = integer("GATE10_BACK_PORT", "8000")
        require(0 < backend_port < 65536, "GATE10_BACK_PORT must be a valid TCP port")

        return cls(
            root=root,
            ingestion_token=required("GATE10_INGESTION_TOKEN"),
            node_id=required("GATE10_NODE_ID"),
            payload_template=payload,
            backend_host=os.environ.get("GATE10_BACK_HOST", "127.0.0.1"),
            backend_port=backend_port,
            database=os.environ.get("GATE10_DB_DATABASE", "iot_platform"),
            db_root_password=os.environ.get("GATE10_DB_ROOT_PASSWORD", "root_password"),
            compose_file=compose_file,
            compose_project=os.environ.get("GATE10_COMPOSE_PROJECT") or None,
            timeout_seconds=timeout_seconds,
            observation_interval_seconds=observation_interval_seconds,
            claim_idle_ms=claim_idle_ms,
            fault_pause_ms=fault_pause_ms,
        )


class Compose:
    """Compose and in-container database/Redis observations for the running topology."""

    RAW_CDC_STREAM = "iot-cdc.iot_platform.raw_event_outboxes"
    RAW_STREAM = "iot.raw-events"
    DLQ_STREAM = "iot.dead-letter-events"
    CDC_GROUP = "outbox-publish-v1"
    RAW_GROUP = "raw-process-v1"

    def __init__(self, runner: CommandRunner, config: HarnessConfig):
        self.runner = runner
        self.config = config

    def command(self, *args: str) -> list[str]:
        prefix = ["docker", "compose", "--file", str(self.config.compose_file)]
        if self.config.compose_project:
            prefix.extend(["--project-name", self.config.compose_project])
        return [*prefix, *args]

    def docker_available(self) -> None:
        self.runner.run(["docker", "info"], timeout=20)
        self.runner.run(self.command("version"), timeout=20)

    def service_container_id(self, service: str) -> str:
        result = self.runner.run(self.command("ps", "-q", service), timeout=20)
        container_id = result.stdout.splitlines()[0] if result.stdout else ""
        require(bool(container_id), f"required Compose service is not running: {service}")
        return container_id

    def service_state(self, service: str) -> dict[str, Any]:
        container_id = self.service_container_id(service)
        result = self.runner.run(["docker", "inspect", container_id], timeout=20)
        inspected = parse_json(result.stdout, f"docker inspect {service}")
        require(isinstance(inspected, list) and inspected, f"docker inspect returned no container: {service}")
        return inspected[0]["State"]

    def require_ready(self) -> None:
        # Health checks exist for the stateful database, Redis, and HTTP receipt boundary. Worker
        # services deliberately have no healthcheck; a running state is the only Compose signal.
        for service in ("db", "redis", "back"):
            state = self.service_state(service)
            require(state.get("Running") is True, f"required service is not running: {service}")
            health = state.get("Health", {}).get("Status")
            require(health == "healthy", f"required service is not healthy: {service} ({health!r})")
        for service in ("debezium", "outbox-cdc-consumer", "raw-consumer"):
            state = self.service_state(service)
            require(state.get("Running") is True, f"required worker is not running: {service}")

    def start(self, *services: str) -> None:
        self.runner.run(self.command("start", *services), timeout=90)

    def stop(self, *services: str) -> None:
        self.runner.run(self.command("stop", *services), timeout=90)

    def restart(self, *services: str) -> None:
        self.runner.run(self.command("restart", *services), timeout=90)

    def exec(self, service: str, args: Iterable[str], *, timeout: int = 60, env: Mapping[str, str] | None = None) -> CommandResult:
        command = self.command("exec", "-T")
        for name, value in (env or {}).items():
            command.extend(["--env", f"{name}={value}"])
        try:
            return self.runner.run([*command, service, *args], timeout=timeout)
        except Gate10Error as exc:
            # CommandRunner includes argv in diagnostics. Never let the DB password cross into a
            # terminal transcript or the JSON evidence when an in-container MySQL command fails.
            message = str(exc)
            for name, value in (env or {}).items():
                message = message.replace(f"{name}={value}", f"{name}=<redacted>")
            raise Gate10Error(message) from exc

    def redis_raw(self, *args: str, timeout: int = 30) -> str:
        return self.exec("redis", ["redis-cli", "--raw", *args], timeout=timeout).stdout

    def redis_json(self, *args: str, timeout: int = 30) -> Any:
        result = self.exec("redis", ["redis-cli", "--json", *args], timeout=timeout)
        return parse_json(result.stdout, f"redis-cli {' '.join(args[:2])}")

    def mysql_json(self, query: str) -> dict[str, Any]:
        result = self.exec(
            "db",
            ["mysql", "--batch", "--skip-column-names", "-u", "root", self.config.database, "-e", query],
            timeout=30,
            env={"MYSQL_PWD": self.config.db_root_password},
        )
        require(bool(result.stdout), "MySQL evidence query returned no result")
        value = parse_json(result.stdout.splitlines()[-1], "MySQL evidence query")
        require(isinstance(value, dict), "MySQL evidence query did not return an object")
        return value

    def mysql_state(self, source_event_id: str) -> dict[str, Any]:
        source = sql_literal("gate10-fault-harness")
        event_id = sql_literal(source_event_id)
        node_id = sql_literal(self.config.node_id)
        query = f"""
SELECT JSON_OBJECT(
  'raw_events', COALESCE((SELECT JSON_ARRAYAGG(JSON_OBJECT(
    'id', r.id, 'status', r.status, 'processed_at', r.processed_at, 'error', r.error
  )) FROM raw_sensor_events r WHERE r.source = {source} AND r.source_event_id = {event_id}), JSON_ARRAY()),
  'outboxes', COALESCE((SELECT JSON_ARRAYAGG(JSON_OBJECT(
    'id', o.id, 'status', o.status, 'attempts', o.attempts, 'published_at', o.published_at, 'last_error', o.last_error
  )) FROM raw_event_outboxes o JOIN raw_sensor_events r ON r.id = o.raw_sensor_event_id
  WHERE r.source = {source} AND r.source_event_id = {event_id}), JSON_ARRAY()),
  'sensor_readings_for_node', (SELECT COUNT(*) FROM sensor_readings sr
    JOIN sensors s ON s.id = sr.sensor_id JOIN devices d ON d.id = s.device_id
    WHERE d.serial_number = {node_id})
) AS evidence;
"""
        return self.mysql_json(query)

    def redis_evidence(self) -> dict[str, Any]:
        streams = {
            "raw_cdc": self.RAW_CDC_STREAM,
            "raw_application": self.RAW_STREAM,
            "dead_letter": self.DLQ_STREAM,
        }
        evidence: dict[str, Any] = {"xlen": {}, "xpending": {}}
        for name, stream in streams.items():
            try:
                evidence["xlen"][name] = int(self.redis_raw("XLEN", stream) or "0")
            except Gate10Error as exc:
                evidence["xlen"][name] = {"observation_error": str(exc)}
        for name, stream, group in (
            ("raw_cdc", self.RAW_CDC_STREAM, self.CDC_GROUP),
            ("raw_application", self.RAW_STREAM, self.RAW_GROUP),
        ):
            try:
                evidence["xpending"][name] = self.redis_json("XPENDING", stream, group)
            except Gate10Error as exc:
                evidence["xpending"][name] = {"observation_error": str(exc)}
        return evidence

    def wait_for(self, description: str, predicate: Callable[[], bool]) -> None:
        deadline = time.monotonic() + self.config.timeout_seconds
        last_error = "predicate was false"
        while time.monotonic() < deadline:
            try:
                if predicate():
                    return
                last_error = "predicate was false"
            except Gate10Error as exc:
                last_error = str(exc)
            time.sleep(self.config.observation_interval_seconds)
        raise Gate10Error(f"timed out waiting for {description}: {last_error}")

    def cdc_entries_for_outbox(self, outbox_id: int) -> list[dict[str, Any]]:
        reply = self.redis_json("XREVRANGE", self.RAW_CDC_STREAM, "+", "-", "COUNT", "1000")
        entries: list[dict[str, Any]] = []
        if not isinstance(reply, list):
            return entries
        for entry in reply:
            if not isinstance(entry, list) or len(entry) < 2:
                continue
            fields = flat_fields(entry[1])
            try:
                change = json.loads(fields.get("value", ""))
            except json.JSONDecodeError:
                continue
            if not isinstance(change, dict):
                continue
            after = change.get("after")
            # Debezium change envelopes carry `after: null` for deletes/tombstones; guard
            # against None before indexing (a null `after` is simply not our insert).
            if not isinstance(after, dict):
                continue
            if after.get("id") == outbox_id:
                entries.append({"id": str(entry[0]), "fields": fields})
        return entries

    def pending_entry(self, stream: str, message_id: str) -> Any:
        return self.redis_json("XPENDING", stream, self.CDC_GROUP, message_id, message_id, "1")

    def dead_letter_for(self, original_id: str) -> dict[str, str] | None:
        reply = self.redis_json("XREVRANGE", self.DLQ_STREAM, "+", "-", "COUNT", "1000")
        if not isinstance(reply, list):
            return None
        for entry in reply:
            if isinstance(entry, list) and len(entry) >= 2:
                fields = flat_fields(entry[1])
                if fields.get("orig_id") == original_id:
                    return fields
        return None

    def run_cdc_once(self, consumer: str, *, claim_idle_ms: int) -> None:
        self.runner.run(
            self.command(
                "run", "--rm", "--no-deps", "--entrypoint", "php", "outbox-cdc-consumer",
                "artisan", "cdc:consume-outboxes", "--once", "--block=0",
                f"--claim-idle={claim_idle_ms}", f"--consumer={consumer}",
            ),
            timeout=self.config.timeout_seconds,
        )

    def start_paused_cdc(self, container_name: str, consumer: str) -> str:
        result = self.runner.run(
            self.command(
                "run", "--detach", "--no-deps", "--name", container_name,
                "--env", "APP_ENV=local",
                "--env", f"GATE10_CDC_PAUSE_AFTER_PUBLISH_MS={self.config.fault_pause_ms}",
                "--entrypoint", "php", "outbox-cdc-consumer",
                "artisan", "cdc:consume-outboxes", "--once", "--block=0", "--claim-idle=0",
                f"--consumer={consumer}",
            ),
            timeout=60,
        )
        container_id = result.stdout.splitlines()[-1] if result.stdout else ""
        require(bool(container_id), "Compose did not return a paused CDC container id")
        return container_id

    def wait_for_checkpoint(self, container_id: str, stream_id: str) -> None:
        marker = "Gate 10 fault checkpoint reached after publish before ack"

        def reached() -> bool:
            # The checkpoint marker is written to the container's STDERR (see Gate10FaultInjection::hook);
            # `docker logs` routes it to this process's stderr, which capture_output separates from stdout.
            # Search both streams so the marker is actually observed.
            result = self.runner.run(["docker", "logs", container_id], timeout=20, check=False)
            logs = result.stdout + result.stderr
            return marker in logs and stream_id in logs

        self.wait_for(f"CDC fault checkpoint for {stream_id}", reached)

    def kill_and_remove(self, container_id: str) -> None:
        self.runner.run(["docker", "kill", container_id], timeout=30, check=False)
        self.runner.run(["docker", "rm", "-f", container_id], timeout=30, check=False)


class FaultHarness:
    """Five independent scenarios; each emits evidence even when its assertions fail."""

    def __init__(self, config: HarnessConfig):
        self.config = config
        self.runner = CommandRunner()
        self.compose = Compose(self.runner, config)
        self.run_id = f"gate10-{uuid.uuid4().hex[:12]}"
        self.expected_readings = self._expected_readings()

    def _expected_readings(self) -> int:
        sensors = self.config.payload_template.get("sensors")
        require(isinstance(sensors, dict) and sensors, "payload template must include non-empty sensors")
        qc = self.config.payload_template.get("qc", {})
        require(isinstance(qc, dict), "payload template field qc must be an object when present")
        if qc.get("valid") is False:
            raise Gate10Error("payload template cannot set payload.qc.valid=false")
        count = sum(
            1 for item in sensors.values()
            if isinstance(item, dict) and isinstance(item.get("value"), (int, float)) and not isinstance(item.get("value"), bool)
        )
        require(count > 0, "payload template must contain at least one numeric sensor value")
        return count

    def event_id(self, scenario: str) -> str:
        return f"{self.run_id}-{scenario}-{uuid.uuid4().hex[:12]}"

    def payload(self, source_event_id: str) -> dict[str, Any]:
        body = copy.deepcopy(self.config.payload_template)
        body["source"] = "gate10-fault-harness"
        body["source_event_id"] = source_event_id
        body["received_at"] = utc_now()
        device = body.setdefault("device", {})
        require(isinstance(device, dict), "payload template field device must be an object when present")
        device["node_id"] = self.config.node_id
        return {"source": body.pop("source"), "source_event_id": body.pop("source_event_id"), "received_at": body.pop("received_at"), "payload": body}

    def submit(self, source_event_id: str) -> dict[str, Any]:
        body = json.dumps(self.payload(source_event_id)).encode("utf-8")
        endpoint = f"http://{self.config.backend_host}:{self.config.backend_port}/api/ingestion/events"
        http_request = request.Request(
            endpoint,
            data=body,
            method="POST",
            headers={
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-Ingestion-Token": self.config.ingestion_token,
                "X-Request-Id": source_event_id,
            },
        )
        try:
            with request.urlopen(http_request, timeout=20) as response:
                status = response.status
                response_body = response.read().decode("utf-8")
        except error.HTTPError as exc:
            status = exc.code
            response_body = exc.read().decode("utf-8", errors="replace")
        except OSError as exc:
            raise Gate10Error(f"ingestion HTTP request failed: {exc}") from exc
        require(status in (200, 201), f"ingestion was not accepted ({status}): {response_body[:500]}")
        response_json = parse_json(response_body, "ingestion response")
        require(isinstance(response_json, dict), "ingestion response was not an object")
        require(response_json.get("status") == "received", f"unexpected ingestion receipt status: {response_json}")
        return response_json

    def state(self, source_event_id: str) -> dict[str, Any]:
        return {
            "captured_at": utc_now(),
            "mysql": self.compose.mysql_state(source_event_id),
            "redis": self.compose.redis_evidence(),
        }

    def outbox_id(self, source_event_id: str) -> int:
        snapshot = self.compose.mysql_state(source_event_id)
        outboxes = snapshot.get("outboxes", [])
        require(isinstance(outboxes, list) and len(outboxes) == 1, f"expected exactly one committed outbox for {source_event_id}: {snapshot}")
        return int(outboxes[0]["id"])

    def assert_committed(self, source_event_id: str) -> int:
        outbox_id = self.outbox_id(source_event_id)
        snapshot = self.compose.mysql_state(source_event_id)
        require(len(snapshot.get("raw_events", [])) == 1, "raw event was not committed")
        require(snapshot["outboxes"][0]["status"] in ("pending", "published"), "outbox is not in a durable accepted state")
        return outbox_id

    def assert_logical_reading_once(self, source_event_id: str, baseline: int) -> None:
        snapshot = self.compose.mysql_state(source_event_id)
        readings = int(snapshot["sensor_readings_for_node"])
        require(readings == baseline + self.expected_readings, (
            f"logical reading count was {readings - baseline}, expected exactly {self.expected_readings}"
        ))
        raw_events = snapshot.get("raw_events", [])
        outboxes = snapshot.get("outboxes", [])
        require(len(raw_events) == 1 and raw_events[0].get("status") == "processed", f"raw event was not processed: {raw_events}")
        require(len(outboxes) == 1 and outboxes[0].get("status") == "published", f"outbox was not published: {outboxes}")

    def wait_for_logical_reading_once(self, source_event_id: str, baseline: int) -> None:
        self.compose.wait_for(
            f"one logical reading for {source_event_id}",
            lambda: self._logical_reading_is_once(source_event_id, baseline),
        )
        self.assert_logical_reading_once(source_event_id, baseline)

    def _logical_reading_is_once(self, source_event_id: str, baseline: int) -> bool:
        snapshot = self.compose.mysql_state(source_event_id)
        return (
            int(snapshot["sensor_readings_for_node"]) == baseline + self.expected_readings
            and len(snapshot.get("raw_events", [])) == 1
            and snapshot["raw_events"][0].get("status") == "processed"
            and len(snapshot.get("outboxes", [])) == 1
            and snapshot["outboxes"][0].get("status") == "published"
        )

    def scenario_a(self, source_event_id: str, baseline: int) -> dict[str, Any]:
        # Hold the CDC consumer only until the receipt is committed and the web container is down;
        # this removes the pre-fault delivery race, then proves the normal CDC/raw path progresses
        # while the web process remains dead.
        self.compose.stop("outbox-cdc-consumer")
        try:
            response = self.submit(source_event_id)
            outbox_id = self.assert_committed(source_event_id)
            self.compose.stop("back")
            self.compose.start("outbox-cdc-consumer")
            self.wait_for_logical_reading_once(source_event_id, baseline)
        finally:
            self.compose.start("back", "outbox-cdc-consumer")
            self.compose.wait_for("back health after Scenario A", lambda: self.compose.service_state("back").get("Health", {}).get("Status") == "healthy")
        return {"accepted_response": response, "outbox_id": outbox_id, "assertion": "durable progress survived back container stop"}

    def _create_dead_pending_cdc(self, source_event_id: str, scenario: str) -> tuple[int, str, str]:
        outbox_id = self.assert_committed(source_event_id)
        self.compose.wait_for(
            f"Debezium CDC entry for outbox {outbox_id}",
            lambda: bool(self.compose.cdc_entries_for_outbox(outbox_id)),
        )
        entries = self.compose.cdc_entries_for_outbox(outbox_id)
        require(len(entries) == 1, f"expected one CDC entry for outbox {outbox_id}, found {entries}")
        stream_id = entries[0]["id"]
        container_name = f"{self.run_id}-{scenario}-paused"
        paused_id = self.compose.start_paused_cdc(container_name, f"{self.run_id}-{scenario}-dead")
        try:
            self.compose.wait_for_checkpoint(paused_id, stream_id)
            pending = self.compose.pending_entry(Compose.RAW_CDC_STREAM, stream_id)
            require(isinstance(pending, list) and len(pending) == 1, f"checkpoint entry was not pending: {pending}")
        except Exception:
            self.compose.kill_and_remove(paused_id)
            raise
        return outbox_id, stream_id, paused_id

    def scenario_b(self, source_event_id: str, baseline: int) -> dict[str, Any]:
        # Stop the normal consumer before submission. Otherwise it could race the one-off fault
        # process and consume the only CDC record before the post-XADD checkpoint is reachable.
        self.compose.stop("outbox-cdc-consumer")
        try:
            response = self.submit(source_event_id)
            outbox_id, stream_id, paused_id = self._create_dead_pending_cdc(source_event_id, "b")
            self.compose.kill_and_remove(paused_id)
            pending_after_kill = self.compose.pending_entry(Compose.RAW_CDC_STREAM, stream_id)
            require(isinstance(pending_after_kill, list) and len(pending_after_kill) == 1, "killed CDC entry was not retained pending")
            self.compose.run_cdc_once(f"{self.run_id}-b-reclaimer", claim_idle_ms=self.config.claim_idle_ms)
            self.wait_for_logical_reading_once(source_event_id, baseline)
            self.compose.wait_for("Scenario B CDC acknowledgement", lambda: self.compose.pending_entry(Compose.RAW_CDC_STREAM, stream_id) == [])
        finally:
            self.compose.start("outbox-cdc-consumer")
        return {"accepted_response": response, "outbox_id": outbox_id, "cdc_stream_id": stream_id, "pending_after_kill": pending_after_kill, "assertion": "XADD-before-XACK pending entry was reclaimed without logical duplicate"}

    def scenario_c(self, source_event_id: str, baseline: int) -> dict[str, Any]:
        # Prevent a fast healthy CDC consumer from draining the event before the Redis outage is
        # injected; the normal service is restarted only after Redis has recovered.
        self.compose.stop("outbox-cdc-consumer")
        try:
            response = self.submit(source_event_id)
            outbox_id = self.assert_committed(source_event_id)
            self.compose.stop("redis")
            durable_during_outage = self.compose.mysql_state(source_event_id)
            require(len(durable_during_outage.get("raw_events", [])) == 1, "raw event was lost during Redis outage")
            require(len(durable_during_outage.get("outboxes", [])) == 1, "outbox was lost during Redis outage")
            self.compose.start("redis")
            self.compose.wait_for("Redis health after Scenario C", lambda: self.compose.service_state("redis").get("Health", {}).get("Status") == "healthy")
            self.compose.start("outbox-cdc-consumer")
            self.wait_for_logical_reading_once(source_event_id, baseline)
        finally:
            self.compose.start("redis", "outbox-cdc-consumer")
        return {"accepted_response": response, "outbox_id": outbox_id, "durable_during_outage": durable_during_outage, "assertion": "committed DB state drained after Redis recovery"}

    def scenario_d(self, source_event_id: str, baseline: int) -> dict[str, Any]:
        # This has no DB outbox by design: the poison record is an intentionally malformed CDC
        # envelope. The normal scenario wrapper still captures MySQL and sensor baselines.
        message_id = self.compose.redis_raw(
            "XADD", Compose.RAW_CDC_STREAM, "*",
            "key", "{not-json}",
            "gate10_source_event_id", source_event_id,
        ).strip()
        require(bool(message_id), "Redis did not return an ID for poison CDC record")
        self.compose.wait_for("Scenario D DLQ record", lambda: self.compose.dead_letter_for(message_id) is not None)
        dlq = self.compose.dead_letter_for(message_id)
        require(dlq is not None, "poison record did not reach the DLQ")
        require(dlq.get("orig_stream") == Compose.RAW_CDC_STREAM, f"wrong DLQ origin: {dlq}")
        require("{not-json}" in dlq.get("payload_json", ""), f"DLQ did not preserve poison payload: {dlq}")
        require(self.compose.pending_entry(Compose.RAW_CDC_STREAM, message_id) == [], "poison CDC record remained pending")
        current = self.compose.mysql_state(source_event_id)
        require(int(current["sensor_readings_for_node"]) == baseline, "poison CDC record created a logical reading")
        return {"poison_cdc_stream_id": message_id, "dlq": dlq, "assertion": "poison CDC record was DLQed then ACKed without wedge"}

    def scenario_e(self, source_event_id: str, baseline: int) -> dict[str, Any]:
        # As in Scenario B, stopping the steady-state owner before submission makes ownership of
        # the fresh CDC entry deterministic for the intentionally killed consumer.
        self.compose.stop("outbox-cdc-consumer")
        try:
            response = self.submit(source_event_id)
            outbox_id, stream_id, paused_id = self._create_dead_pending_cdc(source_event_id, "e")
            self.compose.kill_and_remove(paused_id)
            self.compose.run_cdc_once(f"{self.run_id}-e-reclaimer", claim_idle_ms=self.config.claim_idle_ms)
            self.wait_for_logical_reading_once(source_event_id, baseline)
            self.compose.wait_for("Scenario E XAUTOCLAIM acknowledgement", lambda: self.compose.pending_entry(Compose.RAW_CDC_STREAM, stream_id) == [])
        finally:
            self.compose.start("outbox-cdc-consumer")
        return {"accepted_response": response, "outbox_id": outbox_id, "cdc_stream_id": stream_id, "claim_idle_ms": self.config.claim_idle_ms, "assertion": "dead-consumer pending work was reclaimed with no logical duplicate"}

    def restore(self) -> None:
        # All names are fixed Compose services or the run-namespaced one-off containers above.
        self.compose.start("redis", "back", "outbox-cdc-consumer")
        self.compose.wait_for("healthy topology restoration", self._topology_is_ready)

    def _topology_is_ready(self) -> bool:
        self.compose.require_ready()
        return True

    def run_scenario(self, scenario: str) -> dict[str, Any]:
        source_event_id = self.event_id(scenario)
        result: dict[str, Any] = {
            "scenario": scenario.upper(),
            "source_event_id": source_event_id,
            "status": "FAIL",
        }
        try:
            before = self.state(source_event_id)
            result["before"] = before
            baseline = int(before["mysql"]["sensor_readings_for_node"])
            implementation = getattr(self, f"scenario_{scenario}")
            result["actions"] = implementation(source_event_id, baseline)
            result["status"] = "PASS"
        except Exception as exc:  # retain evidence for an operator instead of failing silently
            result["error"] = str(exc)
        finally:
            try:
                self.restore()
            except Exception as exc:
                result["restore_error"] = str(exc)
                result["status"] = "FAIL"
            try:
                result["after"] = self.state(source_event_id)
            except Exception as exc:
                result["after_observation_error"] = str(exc)
                result["status"] = "FAIL"
        return result
