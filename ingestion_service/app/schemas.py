from __future__ import annotations

from typing import Any, Dict, Optional

from pydantic import BaseModel, ConfigDict, Field


class SensorValue(BaseModel):
    model_config = ConfigDict(extra="allow")

    value: float
    unit: Optional[str] = None
    sensor_model: Optional[str] = None
    status: Optional[str] = None


class DevicePayload(BaseModel):
    model_config = ConfigDict(extra="allow")

    node_id: Optional[str] = None
    firmware_version: Optional[str] = None
    location: Optional[str] = None


class QCPayload(BaseModel):
    model_config = ConfigDict(extra="allow")

    checksum: Optional[str] = None
    valid: Optional[bool] = None


class MqttPayload(BaseModel):
    model_config = ConfigDict(extra="allow")

    device: Optional[DevicePayload] = None
    timestamp: Optional[str] = None
    session: Optional[Dict[str, Any]] = None
    network: Optional[Dict[str, Any]] = None
    sensors: Dict[str, SensorValue]
    qc: Optional[QCPayload] = None


class RawIngestionEvent(BaseModel):
    model_config = ConfigDict(extra="forbid")

    topic: Optional[str] = Field(default=None, max_length=255)
    source: Optional[str] = Field(default="ingestion_service", max_length=255)
    received_at: Optional[str] = None
    payload: Dict[str, Any]
