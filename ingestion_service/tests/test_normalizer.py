from app.normalizer import build_raw_event, derive_source_event_id


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


def test_source_event_identity_falls_back_to_a_canonical_semantic_fingerprint():
    first = {
        "timestamp": "2026-05-14T17:30:00Z",
        "device": {"node_id": "lab-01"},
        "sensors": {"temperature": {"value": 23.47}},
    }
    same_fact_different_key_order = {
        "sensors": {"temperature": {"value": 23.47}},
        "device": {"node_id": "lab-01"},
        "timestamp": "2026-05-14T17:30:00Z",
    }

    assert derive_source_event_id(first, topic="iot/lab/readings") == derive_source_event_id(
        same_fact_different_key_order,
        topic="iot/lab/readings",
    )
