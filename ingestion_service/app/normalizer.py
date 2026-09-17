from __future__ import annotations

from datetime import datetime, timezone
import hashlib
from typing import Any
from uuid import uuid4

from app.schemas import RawIngestionEvent


class MissingEventIdentityError(ValueError):
    """Raised when a payload has neither an explicit event id nor a device+boot+sequence tuple.

    A payload fingerprint is never an acceptable substitute: two legitimately
    identical measurements (same value/time) with different sequence numbers
    must be treated as distinct events, not collapsed by content hash.
    """


def _iso_now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def _identity_part(value: Any) -> str | None:
    if value is None:
        return None

    value = str(value).strip()
    return value or None


def _source_identity(kind: str, *parts: str) -> str:
    candidate = ":".join(("mqtt", kind, *parts))
    if len(candidate) <= 255:
        return candidate

    digest = hashlib.sha256(candidate.encode("utf-8")).hexdigest()
    return f"mqtt:{kind}:{digest}"


def derive_source_event_id(payload: dict[str, Any], *, topic: str | None) -> str:
    """Build a retry-stable identity from an explicit event id or a device+boot+sequence tuple.

    Never falls back to a payload-content fingerprint (see MissingEventIdentityError).
    """
    device = payload.get("device") if isinstance(payload.get("device"), dict) else {}
    device_id = _identity_part(device.get("node_id")) or _identity_part(payload.get("node_id"))
    scope = device_id or _identity_part(topic) or "unknown-device"

    for key in ("event_id", "reading_id"):
        event_id = _identity_part(payload.get(key))
        if event_id:
            return _source_identity("event", scope, event_id)

    session = payload.get("session") if isinstance(payload.get("session"), dict) else {}
    boot_id = (
        _identity_part(session.get("boot_id"))
        or _identity_part(session.get("boot_count"))
        or _identity_part(payload.get("boot_id"))
        or _identity_part(payload.get("boot_count"))
    )
    sequence = (
        _identity_part(session.get("reading_index"))
        or _identity_part(payload.get("sequence"))
        or _identity_part(payload.get("reading_index"))
    )
    if boot_id and sequence:
        return _source_identity("sequence", scope, boot_id, sequence)

    raise MissingEventIdentityError(
        "Payload must include an explicit event_id/reading_id, or a "
        "device + boot/session id + sequence number tuple."
    )


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
