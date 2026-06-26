from __future__ import annotations

import argparse
import logging
from datetime import datetime, timezone
from typing import Any

from app.backend_client import BackendClient, BackendClientError
from app.logging_config import configure_logging
from app.mqtt_client import MQTTIngestionClient
from app.normalizer import build_raw_event
from app.settings import get_settings
from app.validators import PayloadValidationError, validate_payload

logger = logging.getLogger(__name__)


def _sample_payload() -> dict[str, Any]:
    return {
        "device": {
            "node_id": "lab_postgrado_nodo_01",
            "firmware_version": "1.0.0",
            "location": "Laboratorio Posgrado Quimica - UNAB",
        },
        "timestamp": "2026-05-14T17:30:00Z",
        "session": {
            "uptime_ms": 473821,
            "boot_count": 3,
            "reading_index": 147,
        },
        "network": {
            "wifi_rssi_dbm": -67,
            "mqtt_reconnections": 0,
        },
        "sensors": {
            "temperature": {
                "value": 23.47,
                "unit": "C",
                "sensor_model": "DS18B20",
                "status": "ok",
            },
            "dissolved_oxygen": {
                "value": 8.21,
                "unit": "mg/L",
                "sensor_model": "Atlas Scientific DO",
                "status": "ok",
            },
        },
        "qc": {
            "checksum": "a3f9",
            "valid": True,
        },
    }


def run_simulation(topic: str | None = None) -> int:
    settings = get_settings()
    backend_client = BackendClient(
        settings.backend_base_url,
        settings.backend_ingestion_token,
        timeout_seconds=settings.request_timeout_seconds,
    )

    payload = validate_payload(_sample_payload())
    raw_event = build_raw_event(
        payload,
        topic=topic or settings.mqtt_topic,
        received_at=datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
    )
    response = backend_client.send_raw_event(raw_event)
    logger.info(
        "Simulation event accepted by backend event_id=%s status=%s",
        response.get("event_id"),
        response.get("status"),
    )
    return 0


def run_mqtt() -> int:
    settings = get_settings()
    backend_client = BackendClient(
        settings.backend_base_url,
        settings.backend_ingestion_token,
        timeout_seconds=settings.request_timeout_seconds,
    )
    mqtt_client = MQTTIngestionClient(settings, backend_client)
    mqtt_client.run_forever()
    return 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="IoT raw ingestion service")
    parser.add_argument(
        "--simulate",
        action="store_true",
        help="Send one simulated payload to backend and exit.",
    )
    parser.add_argument(
        "--topic",
        type=str,
        default=None,
        help="Override topic for simulation mode.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    settings = get_settings()
    configure_logging(settings.log_level)

    try:
        if args.simulate or settings.ingestion_mode == "simulate":
            return run_simulation(topic=args.topic)

        if settings.ingestion_mode == "mqtt":
            return run_mqtt()

        logger.error("Unsupported INGESTION_MODE=%s", settings.ingestion_mode)
        return 2
    except (PayloadValidationError, BackendClientError) as exc:
        logger.error("Ingestion service failed: %s", exc)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
