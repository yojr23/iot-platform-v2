from __future__ import annotations

import json
import logging
from typing import Any

import paho.mqtt.client as mqtt

from app.backend_client import BackendClient, BackendClientError
from app.normalizer import build_raw_event
from app.settings import Settings
from app.validators import PayloadValidationError, validate_payload

logger = logging.getLogger(__name__)


class MQTTIngestionClient:
    def __init__(self, settings: Settings, backend_client: BackendClient) -> None:
        self._settings = settings
        self._backend_client = backend_client
        self._mqtt = mqtt.Client(client_id=settings.mqtt_client_id)

        if settings.mqtt_username:
            self._mqtt.username_pw_set(settings.mqtt_username, settings.mqtt_password)

        self._mqtt.on_connect = self._on_connect
        self._mqtt.on_message = self._on_message
        self._mqtt.on_disconnect = self._on_disconnect

    def run_forever(self) -> None:
        logger.info(
            "Connecting to MQTT broker host=%s port=%s topic=%s",
            self._settings.mqtt_host,
            self._settings.mqtt_port,
            self._settings.mqtt_topic,
        )

        self._mqtt.connect(self._settings.mqtt_host, self._settings.mqtt_port, keepalive=60)
        self._mqtt.loop_forever()

    def _on_connect(self, client: mqtt.Client, _userdata: Any, _flags: dict[str, Any], rc: int) -> None:
        if rc != 0:
            logger.error("MQTT connection failed with code=%s", rc)
            return

        logger.info("MQTT connected. Subscribing to topic=%s", self._settings.mqtt_topic)
        client.subscribe(self._settings.mqtt_topic, qos=self._settings.mqtt_qos)

    def _on_disconnect(self, _client: mqtt.Client, _userdata: Any, rc: int) -> None:
        if rc != 0:
            logger.warning("MQTT disconnected unexpectedly with code=%s", rc)
        else:
            logger.info("MQTT disconnected cleanly.")

    def _on_message(self, _client: mqtt.Client, _userdata: Any, msg: mqtt.MQTTMessage) -> None:
        topic = msg.topic
        try:
            payload = json.loads(msg.payload.decode("utf-8"))
            payload = validate_payload(payload)
            raw_event = build_raw_event(payload, topic=topic)
            response = self._backend_client.send_raw_event(raw_event)
            logger.info(
                "Raw event sent to backend successfully topic=%s event_id=%s status=%s",
                topic,
                response.get("event_id"),
                response.get("status"),
            )
        except json.JSONDecodeError as exc:
            logger.error("Invalid JSON received on topic=%s error=%s", topic, exc)
        except PayloadValidationError as exc:
            logger.error("Invalid MQTT payload on topic=%s error=%s", topic, exc)
        except BackendClientError as exc:
            logger.error("Backend ingestion failed for topic=%s error=%s", topic, exc)
