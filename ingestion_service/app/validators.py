from __future__ import annotations

from typing import Any


class PayloadValidationError(ValueError):
    """Raised when a payload does not satisfy minimum ingestion requirements."""


def validate_payload(payload: Any) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise PayloadValidationError("Payload must be a JSON object.")

    sensors = payload.get("sensors")
    if not isinstance(sensors, dict) or not sensors:
        raise PayloadValidationError("Payload must include a non-empty sensors object.")

    for sensor_key, sensor_data in sensors.items():
        if not isinstance(sensor_data, dict):
            raise PayloadValidationError(f"Sensor '{sensor_key}' must be an object.")

        if "value" not in sensor_data:
            raise PayloadValidationError(f"Sensor '{sensor_key}' must include a value field.")

        try:
            float(sensor_data["value"])
        except (TypeError, ValueError) as exc:
            raise PayloadValidationError(f"Sensor '{sensor_key}' value must be numeric.") from exc

    qc = payload.get("qc")
    if qc is not None:
        if not isinstance(qc, dict):
            raise PayloadValidationError("Payload qc must be an object when provided.")

        qc_valid = qc.get("valid")
        if qc_valid is not None and not isinstance(qc_valid, bool):
            raise PayloadValidationError("Payload qc.valid must be a boolean when provided.")

    return payload
