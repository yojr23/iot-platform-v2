from __future__ import annotations

from dataclasses import dataclass
from os import getenv
from typing import Optional

from dotenv import load_dotenv

load_dotenv()


def _env_int(name: str, default: int) -> int:
    value = getenv(name)
    if value is None or value == "":
        return default

    try:
        return int(value)
    except ValueError:
        return default


@dataclass(frozen=True)
class Settings:
    mqtt_host: str
    mqtt_port: int
    mqtt_topic: str
    mqtt_username: Optional[str]
    mqtt_password: Optional[str]
    mqtt_client_id: str
    mqtt_qos: int
    backend_base_url: str
    backend_ingestion_token: str
    log_level: str
    ingestion_mode: str
    request_timeout_seconds: int
    spool_path: str
    retry_base_seconds: int
    retry_max_seconds: int
    delivery_batch_size: int


def get_settings() -> Settings:
    return Settings(
        mqtt_host=getenv("MQTT_HOST", "localhost"),
        mqtt_port=_env_int("MQTT_PORT", 1883),
        mqtt_topic=getenv("MQTT_TOPIC", "iot/+/readings"),
        mqtt_username=getenv("MQTT_USERNAME"),
        mqtt_password=getenv("MQTT_PASSWORD"),
        mqtt_client_id=getenv("MQTT_CLIENT_ID", "iot-platform-v2-ingestion"),
        mqtt_qos=_env_int("MQTT_QOS", 1),
        backend_base_url=getenv("BACKEND_BASE_URL", "http://localhost:8000"),
        backend_ingestion_token=getenv("BACKEND_INGESTION_TOKEN", ""),
        log_level=getenv("LOG_LEVEL", "INFO").upper(),
        ingestion_mode=getenv("INGESTION_MODE", "simulate").lower(),
        request_timeout_seconds=_env_int("BACKEND_TIMEOUT_SECONDS", 10),
        spool_path=getenv("INGESTION_SPOOL_PATH", "/data/ingestion-spool.sqlite3"),
        retry_base_seconds=_env_int("INGESTION_RETRY_BASE_SECONDS", 1),
        retry_max_seconds=_env_int("INGESTION_RETRY_MAX_SECONDS", 60),
        delivery_batch_size=_env_int("INGESTION_DELIVERY_BATCH_SIZE", 50),
    )
