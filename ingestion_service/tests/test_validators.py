import pytest

from app.validators import PayloadValidationError, validate_payload


def valid_payload():
    return {
        "device": {
            "node_id": "lab_postgrado_nodo_01",
        },
        "sensors": {
            "temperature": {
                "value": 23.47,
                "unit": "C",
            }
        },
        "qc": {
            "valid": True,
        },
    }


def test_validate_payload_accepts_valid_payload():
    payload = valid_payload()
    validated = validate_payload(payload)
    assert validated == payload


def test_validate_payload_rejects_missing_sensors():
    payload = valid_payload()
    payload.pop("sensors")

    with pytest.raises(PayloadValidationError):
        validate_payload(payload)


def test_validate_payload_rejects_sensor_without_value():
    payload = valid_payload()
    payload["sensors"]["temperature"].pop("value")

    with pytest.raises(PayloadValidationError):
        validate_payload(payload)
