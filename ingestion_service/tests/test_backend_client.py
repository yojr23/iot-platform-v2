from unittest.mock import Mock

from app.backend_client import BackendClient


def test_backend_client_sends_token_header_and_payload():
    fake_response = Mock()
    fake_response.status_code = 201
    fake_response.json.return_value = {
        "message": "Raw sensor event stored successfully",
        "event_id": 123,
        "status": "received",
    }
    fake_response.text = ""

    session = Mock()
    session.post.return_value = fake_response

    client = BackendClient(
        "http://backend:8000",
        "secret-token",
        timeout_seconds=5,
        session=session,
    )

    payload = {
        "topic": "iot/lab_postgrado_nodo_01/readings",
        "received_at": "2026-05-14T17:30:00Z",
        "payload": {"sensors": {"temperature": {"value": 20.0}}},
    }

    response = client.send_raw_event(payload)

    assert response["event_id"] == 123
    session.post.assert_called_once()
    _, kwargs = session.post.call_args

    assert kwargs["json"] == payload
    assert kwargs["headers"]["X-Ingestion-Token"] == "secret-token"
    assert kwargs["headers"]["Content-Type"] == "application/json"
    assert kwargs["timeout"] == 5
