from __future__ import annotations

from datetime import datetime, timezone
from typing import Any
from uuid import uuid4

from app.schemas import RawIngestionEvent


def _iso_now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def build_raw_event(
    payload: dict[str, Any],
    *,
    topic: str | None,
    received_at: str | None = None,
    source: str = "ingestion_service",
    source_event_id: str | None = None,
) -> dict[str, Any]:
    event = RawIngestionEvent(
        topic=topic,
        source=source,
        source_event_id=source_event_id or str(uuid4()),
        received_at=received_at or payload.get("timestamp") or _iso_now(),
        payload=payload,
    )

    return event.model_dump()
