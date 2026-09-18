import time

from app.spool import DurableEventSpool, redact_secrets


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


def test_mark_failed_dead_letters_after_max_attempts(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3", max_attempts=5)
    spool.enqueue(event())

    # Exhaust the configured max_attempts.
    for i in range(1, 6):
        spool.mark_failed("event-1", f"error-{i}", time.time() + 60 * i)

    # Event should be removed from pending
    assert spool.claim_due(limit=10) == []

    # Event should appear in dead_letters
    dead = spool.dead_letters()
    assert len(dead) == 1
    assert dead[0]["source_event_id"] == "event-1"
    assert dead[0]["attempts"] == 5
    spool.close()


def test_mark_failed_respects_custom_max_attempts(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3", max_attempts=3)
    spool.enqueue(event())

    for i in range(1, 4):
        spool.mark_failed("event-1", f"error-{i}", time.time() + 60 * i)

    dead = spool.dead_letters()
    assert len(dead) == 1
    assert dead[0]["attempts"] == 3
    spool.close()


def test_dead_letters_returns_empty_when_no_exhausted_events(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    spool.enqueue(event())

    assert spool.dead_letters() == []
    spool.close()


def test_quarantine_persists_sanitized_record_with_reason_and_hash(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")

    spool.quarantine(topic="iot/lab-01/readings", reason="invalid_json: bad token", raw_payload=b"{not json")

    records = spool.quarantined()
    assert len(records) == 1
    record = records[0]
    assert record["topic"] == "iot/lab-01/readings"
    assert record["reason"] == "invalid_json: bad token"
    assert record["payload_snippet"] == "{not json"
    assert len(record["payload_hash"]) == 64  # sha256 hex digest, not the raw payload itself
    spool.close()


def test_quarantine_snippet_is_capped_not_unbounded(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    huge_payload = b"x" * 50_000

    spool.quarantine(topic="iot/lab-01/readings", reason="invalid_json: huge", raw_payload=huge_payload)

    record = spool.quarantined()[0]
    assert len(record["payload_snippet"]) <= 2000
    spool.close()


def test_quarantine_evicts_oldest_past_capacity(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3", quarantine_capacity=2)

    for i in range(3):
        spool.quarantine(topic="t", reason=f"reason-{i}", raw_payload=f"payload-{i}".encode())

    records = spool.quarantined()
    assert len(records) == 2
    # Oldest (reason-0) was evicted; the two most recent survive.
    assert [r["reason"] for r in records] == ["reason-1", "reason-2"]


def test_redact_secrets_masks_json_and_kv_forms():
    masked = redact_secrets('{"api_key":"SECRET123","value":22.5,"token":"abc.def"}')
    assert "SECRET123" not in masked
    assert "abc.def" not in masked
    assert "22.5" in masked  # non-secret fields untouched

    kv = redact_secrets("api_key=SECRET123&value=22.5&password=hunter2")
    assert "SECRET123" not in kv
    assert "hunter2" not in kv
    assert "value=22.5" in kv


def test_quarantine_redacts_secrets_before_persisting(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    payload = b'{"api_key":"SUPERSECRETKEY","value":99,"x-device-key":"dev-secret"}'

    spool.quarantine(topic="iot/lab-01/readings", reason="invalid_json", raw_payload=payload)

    record = spool.quarantined()[0]
    assert "SUPERSECRETKEY" not in record["payload_snippet"]
    assert "dev-secret" not in record["payload_snippet"]
    assert "[REDACTED]" in record["payload_snippet"]
    # Hash is of the true payload, so it still fingerprints the original for dedup/inspection.
    assert len(record["payload_hash"]) == 64
    spool.close()
    spool.close()


def test_redact_multi_word_and_nested_secrets():
    # Multi-word secret VALUE (spaces) must be fully masked, not just the first token.
    masked = redact_secrets('{"password":"correct horse battery staple","value":1}')
    assert "correct" not in masked
    assert "staple" not in masked
    assert "1" in masked

    # Nested secret.
    nested = redact_secrets('{"outer":{"api_key":"secret with spaces"}}')
    assert "secret with spaces" not in nested
    assert "with" not in nested

    # Secret inside a list of objects.
    listed = redact_secrets('{"items":[{"token":"aaa bbb ccc"},{"value":2}]}')
    assert "aaa bbb ccc" not in listed
    assert "bbb" not in listed
    assert "2" in listed


def test_redact_bearer_and_mixed_case_authorization():
    masked = redact_secrets('{"authorization":"Bearer eyJhbGciOi.payload.sig"}')
    assert "eyJhbGciOi.payload.sig" not in masked

    upper = redact_secrets('{"Authorization":"Bearer tok123"}')
    assert "tok123" not in upper

    # Malformed / header-style (not JSON) fallback still strips the bearer token.
    header = redact_secrets("Authorization: Bearer eyJhbGciOi.payload.sig\nx-other: keep")
    assert "eyJhbGciOi.payload.sig" not in header
    assert "keep" in header


def test_redact_device_key_variants_and_escaped_json():
    for key in ("x-device-key", "api-key", "api_key", "X-Device-Key"):
        masked = redact_secrets('{"%s":"secret with spaces"}' % key)
        assert "secret with spaces" not in masked, key

    # Escaped JSON string value must not leak.
    escaped = redact_secrets('{"token":"a\\"b c d"}')
    assert "b c d" not in escaped


def test_redact_malformed_json_with_authorization():
    # Missing closing brace — not valid JSON, must use conservative fallback.
    masked = redact_secrets('{"password":"multi word secret", "value": 3')
    assert "multi word secret" not in masked


def test_quarantine_multi_word_secret_does_not_survive(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    secrets = ["correct horse battery staple", "eyJhbGciOi.payload.sig", "dev key with spaces"]
    payload = (
        '{"password":"correct horse battery staple",'
        '"authorization":"Bearer eyJhbGciOi.payload.sig",'
        '"x-device-key":"dev key with spaces","value":42}'
    ).encode()

    spool.quarantine(topic="t", reason="invalid_json", raw_payload=payload)
    snippet = spool.quarantined()[0]["payload_snippet"]

    for secret in secrets:
        assert secret not in snippet
    for word in ("horse", "battery", "staple", "payload", "spaces"):
        assert word not in snippet
    assert "42" in snippet
    spool.close()


def test_quarantine_50k_payload_stays_bounded_after_sanitizing(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    payload = ('{"password":"' + "s" * 50_000 + '"}').encode()

    spool.quarantine(topic="t", reason="invalid_json", raw_payload=payload)
    record = spool.quarantined()[0]

    assert len(record["payload_snippet"]) <= 2000
    assert "sssss" not in record["payload_snippet"]
    spool.close()


def test_mark_failed_does_not_dead_letter_below_max_attempts(tmp_path):
    now = [100.0]
    spool = DurableEventSpool(
        tmp_path / "spool.sqlite3", max_attempts=5, clock=lambda: now[0]
    )
    spool.enqueue(event())

    spool.mark_failed("event-1", "error-1", now[0] + 60)

    # It remains queued but is not due until the scheduled retry time.
    assert spool.claim_due(limit=10) == []
    assert spool.dead_letters() == []

    now[0] += 60
    pending = spool.claim_due(limit=10)
    assert len(pending) == 1
    spool.close()
