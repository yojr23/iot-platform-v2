import json
from types import SimpleNamespace
from unittest.mock import Mock

from app.backend_client import BackendClient, BackendClientError
from app.delivery_worker import DeliveryWorker
from app.mqtt_client import MQTTIngestionClient
from app.spool import DurableEventSpool
from tests.test_mqtt_client import _message, _settings


def _worker(spool, backend):
    return DeliveryWorker(spool, backend, retry_base_seconds=1, retry_max_seconds=60, batch_size=10)


def _client(status):
    resp = Mock(status_code=status, text="x")
    return BackendClient("http://b", "tok", session=Mock(post=Mock(return_value=resp)))


def _send(status):
    try:
        _client(status).send_raw_event({})
    except BackendClientError as e:
        return e.retryable


def test_error_classification():
    assert [_send(c) for c in (500, 503, 408, 429, 400, 422, 401)] == [True] * 4 + [False] * 3
    try:
        BackendClient("http://b", "").send_raw_event({})
    except BackendClientError as e:
        assert e.retryable is False


def test_500_consecutive_503_never_dead_letter(tmp_path):
    now = [0.0]
    spool = DurableEventSpool(tmp_path / "s.sqlite3", max_attempts=20, clock=lambda: now[0])
    spool.enqueue({"source_event_id": "e1"})
    worker = _worker(spool, SimpleNamespace(send_raw_event=lambda e: _client(503).send_raw_event(e)))
    for _ in range(500):
        now[0] += 61
        worker.run_once()
    assert spool.dead_letters() == []
    assert spool._connection.execute("SELECT attempts FROM pending_events").fetchone()[0] == 500
    # backend recovers
    ok = SimpleNamespace(send_raw_event=lambda e: {"status": "received"})
    now[0] += 61
    _worker(spool, ok).run_once()
    assert spool._connection.execute("SELECT COUNT(*) FROM pending_events").fetchone()[0] == 0
    spool.close()


def test_422_dead_letters_then_replay_delivers(tmp_path):
    spool = DurableEventSpool(tmp_path / "s.sqlite3")
    spool.enqueue({"source_event_id": "e1"})
    _worker(spool, SimpleNamespace(send_raw_event=lambda e: _client(422).send_raw_event(e))).run_once()
    assert [d["source_event_id"] for d in spool.dead_letters()] == ["e1"]

    assert spool.replay_dead_letters("e1") == 1
    assert spool.replay_dead_letters() == 0  # idempotent
    assert spool.dead_letters() == []
    sent = []
    _worker(spool, SimpleNamespace(send_raw_event=lambda e: sent.append(e) or {})).run_once()
    assert sent == [{"source_event_id": "e1"}]
    spool.close()


def test_topic_node_mismatch_is_quarantined(tmp_path):
    spool = DurableEventSpool(tmp_path / "s.sqlite3")
    client = MQTTIngestionClient(_settings(), spool)
    payload = {
        "device": {"node_id": "nodo_a"},
        "timestamp": "2026-05-14T17:30:00Z",
        "session": {"uptime_ms": 1, "boot_count": 1, "reading_index": 1},
        "sensors": {"temperature": {"value": 1.0, "unit": "C", "status": "ok"}},
    }
    client._on_message(None, None, _message("iot/nodo_b/readings", json.dumps(payload).encode()))
    assert spool.claim_due(10) == []
    assert spool.quarantined()[0]["reason"].startswith("node_mismatch")
    client._on_message(None, None, _message("iot/nodo_a/readings", json.dumps(payload).encode()))
    assert len(spool.claim_due(10)) == 1
    spool.close()
