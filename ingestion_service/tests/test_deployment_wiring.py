from pathlib import Path


ROOT = Path(__file__).parents[2]


def _ingestion_service_block() -> str:
    compose = (ROOT / "docker-compose.yml").read_text(encoding="utf-8")
    return compose.split("\n  ingestion:\n", 1)[1].split("\n\nvolumes:\n", 1)[0]


def test_ingestion_compose_uses_settings_environment_names_and_persistent_spool():
    block = _ingestion_service_block()

    assert "MQTT_HOST:" in block
    assert "MQTT_HOST:?MQTT_HOST is required" in block
    assert "MQTT_PORT:" in block
    assert "BACKEND_BASE_URL: http://back:8000" in block
    assert "BACKEND_INGESTION_TOKEN:" in block
    assert "INGESTION_MODE: mqtt" in block
    assert "INGESTION_SPOOL_PATH: /app/spool/ingestion-spool.sqlite3" in block
    assert "INGESTION_RETRY_BASE_SECONDS:" in block
    assert "INGESTION_RETRY_MAX_SECONDS:" in block
    assert "INGESTION_MAX_DELIVERY_ATTEMPTS:" in block
    assert "MQTT_BROKER_HOST:" not in block
    assert "MQTT_DELIVERY_URL:" not in block
    assert "MQTT_SPOOL_DIR:" not in block


def test_ingestion_image_starts_configured_mode_instead_of_forcing_simulation():
    dockerfile = (ROOT / "ingestion_service" / "Dockerfile").read_text(encoding="utf-8")

    assert 'CMD ["python", "-m", "app.main"]' in dockerfile
    assert "--simulate" not in dockerfile
