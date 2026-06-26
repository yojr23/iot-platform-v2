from app.normalizer import build_raw_event


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
