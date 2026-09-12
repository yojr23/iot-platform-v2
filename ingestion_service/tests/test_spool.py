import time

from app.spool import DurableEventSpool


def event(source_event_id: str = "event-1") -> dict:
    return {"source_event_id": source_event_id, "payload": {"temperature": 22.5}}


def test_events_persist_after_sqlite_reopen(tmp_path):
    path = tmp_path / "spool.sqlite3"
    spool = DurableEventSpool(path)
    spool.enqueue(event())
    spool.close()

    reopened = DurableEventSpool(path)
    pending = reopened.claim_due(limit=10)

    assert [item.source_event_id for item in pending] == ["event-1"]
    assert pending[0].event == event()
    reopened.close()


def test_enqueue_does_not_duplicate_source_event_id(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    spool.enqueue(event())
    spool.enqueue(event())

    assert len(spool.claim_due(limit=10)) == 1
    spool.close()


def test_future_retries_are_not_claimed(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    spool.enqueue(event())
    spool.mark_failed("event-1", "backend unavailable", time.time() + 60)

    assert spool.claim_due(limit=10) == []
    spool.close()


def test_mark_delivered_deletes_event(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    spool.enqueue(event())
    spool.mark_delivered("event-1")

    assert spool.claim_due(limit=10) == []
    spool.close()


def test_mark_failed_increments_attempts_and_schedules_retry(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    spool.enqueue(event())
    retry_at = time.time() + 60
    spool.mark_failed("event-1", "backend unavailable", retry_at)

    row = spool._connection.execute(
        "SELECT attempts, next_attempt_at, last_error FROM pending_events WHERE source_event_id = ?",
        ("event-1",),
    ).fetchone()
    assert row == (1, retry_at, "backend unavailable")
    spool.close()


def test_claim_is_exclusive_until_its_lease_expires(tmp_path):
    now = [100.0]
    path = tmp_path / "spool.sqlite3"
    first = DurableEventSpool(path, lease_seconds=30, clock=lambda: now[0])
    second = DurableEventSpool(path, lease_seconds=30, clock=lambda: now[0])
    first.enqueue(event())

    first_claim = first.claim_due(limit=10)
    second_claim = second.claim_due(limit=10)

    assert [item.source_event_id for item in first_claim] == ["event-1"]
    assert second_claim == []
    first.close()
    second.close()


def test_expired_claim_is_recovered_after_crash(tmp_path):
    now = [100.0]
    path = tmp_path / "spool.sqlite3"
    crashed = DurableEventSpool(path, lease_seconds=30, clock=lambda: now[0])
    crashed.enqueue(event())
    assert len(crashed.claim_due(limit=10)) == 1
    crashed.close()

    now[0] = 131.0
    recovered = DurableEventSpool(path, lease_seconds=30, clock=lambda: now[0])
    assert [item.source_event_id for item in recovered.claim_due(limit=10)] == ["event-1"]
    recovered.close()


def test_creates_missing_spool_parent_directory(tmp_path):
    path = tmp_path / "missing" / "nested" / "spool.sqlite3"

    spool = DurableEventSpool(path)

    assert path.exists()
    spool.close()
