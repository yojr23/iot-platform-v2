from __future__ import annotations

import json
import logging
from typing import Any

import paho.mqtt.client as mqtt

from app.normalizer import MissingEventIdentityError, build_raw_event, derive_source_event_id
from app.settings import Settings
from app.spool import DurableEventSpool
from app.validators import PayloadValidationError, validate_payload

logger = logging.getLogger(__name__)


class MQTTIngestionClient:
    def __init__(self, settings: Settings, spool: DurableEventSpool) -> None:
        self._settings = settings
        self._spool = spool
        # Callback API v2 (paho-mqtt >= 2). Persistent session (clean_session=False by default)
        # with a stable client id: the broker retains QoS-1 messages for this client while the
        # subscriber is offline, and redelivers on reconnect. Automatic reconnect keeps the same
        # identity, and _on_connect re-subscribes on every (re)connection.
        self._mqtt = mqtt.Client(
            callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
            client_id=settings.mqtt_client_id,
            clean_session=settings.mqtt_clean_session,
        )

        if settings.mqtt_username:
            self._mqtt.username_pw_set(settings.mqtt_username, settings.mqtt_password)

        self._mqtt.on_connect = self._on_connect
        self._mqtt.on_message = self._on_message
        self._mqtt.on_disconnect = self._on_disconnect

    def run_forever(self) -> None:
        logger.info(
            "Connecting to MQTT broker host=%s port=%s topic=%s clean_session=%s client_id=%s",
            self._settings.mqtt_host,
            self._settings.mqtt_port,
            self._settings.mqtt_topic,
            self._settings.mqtt_clean_session,
            self._settings.mqtt_client_id,
        )

        # Bounded auto-reconnect keeps the same client id/session across broker blips.
        self._mqtt.reconnect_delay_set(min_delay=1, max_delay=30)
        self._mqtt.connect(self._settings.mqtt_host, self._settings.mqtt_port, keepalive=60)
        self._mqtt.loop_forever()

    def _on_connect(self, client: mqtt.Client, _userdata: Any, _flags: Any, reason_code: Any, _properties: Any = None) -> None:
        # paho v2 passes a ReasonCode; is_failure is the version-agnostic success check.
        if getattr(reason_code, "is_failure", False):
            logger.error("MQTT connection failed with reason=%s", reason_code)
            return

        # Re-subscribe on every (re)connect. With a persistent session the broker restores the
        # subscription itself, but re-subscribing is idempotent and covers a clean-session run too.
        logger.info("MQTT connected. Subscribing to topic=%s", self._settings.mqtt_topic)
        client.subscribe(self._settings.mqtt_topic, qos=self._settings.mqtt_qos)

    def _on_disconnect(self, _client: mqtt.Client, _userdata: Any, _flags: Any, reason_code: Any = None, _properties: Any = None) -> None:
        if reason_code is not None and getattr(reason_code, "is_failure", False):
            logger.warning("MQTT disconnected unexpectedly with reason=%s", reason_code)
        else:
            logger.info("MQTT disconnected cleanly.")

    def _on_message(self, _client: mqtt.Client, _userdata: Any, msg: mqtt.MQTTMessage) -> None:
        topic = msg.topic
        try:
            payload = json.loads(msg.payload.decode("utf-8"))
            payload = validate_payload(payload)
            source_event_id = derive_source_event_id(payload, topic=topic)
            raw_event = build_raw_event(
                payload,
                topic=topic,
                source_event_id=source_event_id,
            )
            self._spool.enqueue(raw_event)
            logger.info(
                "Raw event durably queued for backend delivery topic=%s source_event_id=%s",
                topic,
                source_event_id,
            )
        except json.JSONDecodeError as exc:
            logger.error("Invalid JSON received on topic=%s error=%s", topic, exc)
            self._spool.quarantine(topic=topic, reason=f"invalid_json: {exc}", raw_payload=msg.payload)
        except PayloadValidationError as exc:
            logger.error("Invalid MQTT payload on topic=%s error=%s", topic, exc)
            self._spool.quarantine(topic=topic, reason=f"invalid_payload: {exc}", raw_payload=msg.payload)
        except MissingEventIdentityError as exc:
            logger.error("Missing event identity on topic=%s error=%s", topic, exc)
            self._spool.quarantine(topic=topic, reason=f"missing_identity: {exc}", raw_payload=msg.payload)
