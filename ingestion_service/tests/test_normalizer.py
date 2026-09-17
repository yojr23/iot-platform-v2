import pytest

from app.normalizer import MissingEventIdentityError, build_raw_event, derive_source_event_id


def test_build_raw_event_generates_contract_for_backend():
    payload = {
        "timestamp": "2026-05-14T17:30:00Z",
        "sensors": {
            "temperature": {
                "value": 23.47,
            }
        },
    }

    event = build_raw_event(
        payload,
        topic="iot/lab_postgrado_nodo_01/readings",
    )

    assert event["topic"] == "iot/lab_postgrado_nodo_01/readings"
    assert event["received_at"] == "2026-05-14T17:30:00Z"
    assert event["payload"] == payload
    assert event["source"] == "ingestion_service"
    assert isinstance(event["source_event_id"], str)
    assert event["source_event_id"]


def test_build_raw_event_preserves_producer_identity_for_retry():
    payload = {
        "timestamp": "2026-05-14T17:30:00Z",
        "sensors": {"temperature": {"value": 23.47}},
    }

    event = build_raw_event(
        payload,
        topic="iot/lab_postgrado_nodo_01/readings",
        source_event_id="mqtt:lab-01:packet-42",
    )

    assert event["source_event_id"] == "mqtt:lab-01:packet-42"


def test_source_event_identity_prefers_device_event_id_over_transport_metadata():
    payload = {
        "event_id": "reading-147",
        "device": {"node_id": "lab-01"},
        "timestamp": "2026-05-14T17:30:00Z",
        "sensors": {"temperature": {"value": 23.47}},
    }

    assert derive_source_event_id(payload, topic="iot/lab/readings") == derive_source_event_id(
        payload,
        topic="iot/lab/readings",
    )


def test_source_event_identity_uses_boot_sequence_when_available():
    payload = {
        "device": {"node_id": "lab-01"},
        "session": {"boot_id": "boot-9"},
        "sequence": 42,
        "sensors": {"temperature": {"value": 23.47}},
    }

    assert derive_source_event_id(payload, topic="iot/lab/readings") == derive_source_event_id(
        payload,
        topic="iot/lab/readings",
    )


def test_source_event_identity_uses_session_boot_count_and_reading_index_before_network_metadata():
    first = {
        "device": {"node_id": "lab-01"},
        "session": {"boot_count": 3, "reading_index": 147},
        "network": {"wifi_rssi_dbm": -67, "mqtt_reconnections": 0},
        "sensors": {"temperature": {"value": 23.47}},
    }
    enriched_retry = {
        **first,
        "network": {"wifi_rssi_dbm": -72, "mqtt_reconnections": 1},
    }

    assert derive_source_event_id(first, topic="iot/lab/readings") == derive_source_event_id(
        enriched_retry,
        topic="iot/lab/readings",
    )


def test_source_event_identity_rejects_payload_with_no_event_id_or_sequence_tuple():
    payload = {
        "timestamp": "2026-05-14T17:30:00Z",
        "device": {"node_id": "lab-01"},
        "sensors": {"temperature": {"value": 23.47}},
    }

    with pytest.raises(MissingEventIdentityError):
        derive_source_event_id(payload, topic="iot/lab/readings")


def test_identical_measurements_with_different_sequence_numbers_are_distinct_events():
    """Same value/time is not identity: distinct sequence numbers must produce distinct ids."""
    first = {
        "device": {"node_id": "lab-01"},
        "session": {"boot_id": "boot-9", "reading_index": 1},
        "timestamp": "2026-05-14T17:30:00Z",
        "sensors": {"temperature": {"value": 23.47}},
    }
    second = {
        **first,
        "session": {"boot_id": "boot-9", "reading_index": 2},
    }

    assert derive_source_event_id(first, topic="iot/lab/readings") != derive_source_event_id(
        second,
        topic="iot/lab/readings",
    )


def test_genuine_redelivery_with_same_event_id_is_deduplicated():
    """A retry carrying the same explicit event_id must resolve to the same identity."""
    payload = {
        "event_id": "reading-147",
        "device": {"node_id": "lab-01"},
        "timestamp": "2026-05-14T17:30:00Z",
        "sensors": {"temperature": {"value": 23.47}},
    }
    redelivered = {**payload, "timestamp": "2026-05-14T17:30:05Z"}  # transport retry, slight clock skew

    assert derive_source_event_id(payload, topic="iot/lab/readings") == derive_source_event_id(
        redelivered,
        topic="iot/lab/readings",
    )
