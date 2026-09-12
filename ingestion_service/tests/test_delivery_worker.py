from app.backend_client import BackendClientError
from app.delivery_worker import DeliveryWorker, retry_delay
from app.spool import DurableEventSpool


def event(source_event_id: str = "event-1") -> dict:
    return {"source_event_id": source_event_id, "payload": {"temperature": 22.5}}


class RecordingBackend:
    def __init__(self, error: Exception | None = None):
        self.error = error
        self.events: list[dict] = []

    def send_raw_event(self, raw_event: dict) -> dict:
        self.events.append(raw_event)
        if self.error:
            raise self.error
        return {"status": "received"}


def test_run_once_deletes_successful_event(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    spool.enqueue(event())
    backend = RecordingBackend()

    DeliveryWorker(spool, backend, retry_base_seconds=1, retry_max_seconds=60, batch_size=10).run_once()

    assert backend.events == [event()]
    assert spool.claim_due(limit=10) == []
    spool.close()


def test_run_once_keeps_transient_failure_and_increments_attempts(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    spool.enqueue(event())
    backend = RecordingBackend(BackendClientError("backend unavailable"))

    DeliveryWorker(spool, backend, retry_base_seconds=1, retry_max_seconds=60, batch_size=10).run_once()

    row = spool._connection.execute(
        "SELECT attempts, next_attempt_at, last_error FROM pending_events WHERE source_event_id = ?", ("event-1",)
    ).fetchone()
    assert row[0] == 1
    assert row[1] > 0
    assert row[2] == "backend unavailable"
    spool.close()


def test_retry_delay_is_capped_exponential_backoff():
    assert retry_delay(attempt=1, base=2, maximum=10) == 2
    assert retry_delay(attempt=3, base=2, maximum=10) == 8
    assert retry_delay(attempt=9, base=2, maximum=10) == 10


def test_reopened_spool_is_delivered_by_worker(tmp_path):
    path = tmp_path / "spool.sqlite3"
    first = DurableEventSpool(path)
    first.enqueue(event())
    first.close()
    reopened = DurableEventSpool(path)
    backend = RecordingBackend()

    DeliveryWorker(reopened, backend, retry_base_seconds=1, retry_max_seconds=60, batch_size=10).run_once()

    assert backend.events == [event()]
    assert reopened.claim_due(limit=10) == []
    reopened.close()
