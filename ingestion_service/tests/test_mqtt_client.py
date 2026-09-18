import json
from types import SimpleNamespace

from app.mqtt_client import MQTTIngestionClient
from app.settings import Settings
from app.spool import DurableEventSpool


def _settings() -> Settings:
    return Settings(
        mqtt_host="localhost",
        mqtt_port=1883,
        mqtt_topic="iot/+/readings",
        mqtt_username=None,
        mqtt_password=None,
        mqtt_client_id="test-client",
        mqtt_qos=1,
        mqtt_clean_session=False,
        backend_base_url="http://localhost:8000",
        backend_ingestion_token="token",
        log_level="INFO",
        ingestion_mode="mqtt",
        request_timeout_seconds=10,
        spool_path="unused",
        retry_base_seconds=1,
        retry_max_seconds=60,
        delivery_batch_size=50,
        max_delivery_attempts=20,
    )


def _message(topic: str, payload: bytes):
    return SimpleNamespace(topic=topic, payload=payload)


def test_malformed_json_is_quarantined_and_not_enqueued(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    client = MQTTIngestionClient(_settings(), spool)

    client._on_message(None, None, _message("iot/lab-01/readings", b"{not json"))

    assert spool.claim_due(limit=10) == []
    records = spool.quarantined()
    assert len(records) == 1
    assert records[0]["topic"] == "iot/lab-01/readings"
    assert records[0]["reason"].startswith("invalid_json")
    spool.close()


def test_invalid_payload_is_quarantined_and_not_enqueued(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    client = MQTTIngestionClient(_settings(), spool)
    payload = json.dumps({"device": {"node_id": "lab-01"}}).encode("utf-8")  # missing sensors

    client._on_message(None, None, _message("iot/lab-01/readings", payload))

    assert spool.claim_due(limit=10) == []
    records = spool.quarantined()
    assert len(records) == 1
    assert records[0]["reason"].startswith("invalid_payload")
    spool.close()


def test_payload_missing_event_identity_is_quarantined_and_not_enqueued(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    client = MQTTIngestionClient(_settings(), spool)
    payload = json.dumps(
        {
            "device": {"node_id": "lab-01"},
            "sensors": {"temperature": {"value": 23.4}},
        }
    ).encode("utf-8")  # no event_id/reading_id and no boot+sequence tuple

    client._on_message(None, None, _message("iot/lab-01/readings", payload))

    assert spool.claim_due(limit=10) == []
    records = spool.quarantined()
    assert len(records) == 1
    assert records[0]["reason"].startswith("missing_identity")
    spool.close()


def test_valid_message_is_enqueued_and_creates_no_quarantine_record(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    client = MQTTIngestionClient(_settings(), spool)
    payload = json.dumps(
        {
            "event_id": "reading-1",
            "device": {"node_id": "lab-01"},
            "sensors": {"temperature": {"value": 23.4}},
        }
    ).encode("utf-8")

    client._on_message(None, None, _message("iot/lab-01/readings", payload))

    assert len(spool.claim_due(limit=10)) == 1
    assert spool.quarantined() == []
    spool.close()


def test_client_uses_persistent_session_and_stable_id(tmp_path):
    """Persistent session contract: stable non-empty client id + clean_session=False so the broker
    queues QoS-1 messages for us while the subscriber is offline."""
    import paho.mqtt.client as mqtt

    settings = _settings()
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    client = MQTTIngestionClient(settings, spool)

    assert settings.mqtt_client_id  # non-empty, stable
    assert settings.mqtt_clean_session is False
    # paho v2 stores clean_session on the client; verify persistent session was requested.
    assert client._mqtt._clean_session is False
    assert client._mqtt._client_id.decode() == settings.mqtt_client_id
    spool.close()


def test_on_connect_resubscribes_with_configured_qos(tmp_path):
    """Every (re)connect must re-establish the QoS-1 subscription so a reconnect resumes delivery."""
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    client = MQTTIngestionClient(_settings(), spool)

    subscribed = []
    fake_client = SimpleNamespace(subscribe=lambda topic, qos: subscribed.append((topic, qos)))
    ok = SimpleNamespace(is_failure=False)

    client._on_connect(fake_client, None, None, ok)

    assert subscribed == [("iot/+/readings", 1)]
    spool.close()


def test_on_connect_failure_does_not_subscribe(tmp_path):
    spool = DurableEventSpool(tmp_path / "spool.sqlite3")
    client = MQTTIngestionClient(_settings(), spool)

    subscribed = []
    fake_client = SimpleNamespace(subscribe=lambda topic, qos: subscribed.append((topic, qos)))
    fail = SimpleNamespace(is_failure=True)

    client._on_connect(fake_client, None, None, fail)

    assert subscribed == []
    spool.close()
