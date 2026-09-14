from __future__ import annotations

from dataclasses import dataclass
import json
from os import PathLike
from pathlib import Path
import sqlite3
import threading
import time
from typing import Any, Callable
from uuid import uuid4


@dataclass(frozen=True)
class PendingEvent:
    source_event_id: str
    event: dict[str, Any]
    attempts: int
    next_attempt_at: float
    claim_token: str


class DurableEventSpool:
    """The durable, local owner of MQTT events awaiting backend delivery."""

    def __init__(
        self,
        path: str | PathLike[str],
        *,
        lease_seconds: float = 30,
        max_attempts: int = 20,
        clock: Callable[[], float] = time.time,
    ) -> None:
        database_path = Path(path)
        database_path.parent.mkdir(parents=True, exist_ok=True)
        self._connection = sqlite3.connect(str(database_path), check_same_thread=False)
        self._lock = threading.RLock()
        self._lease_seconds = lease_seconds
        self._max_attempts = max_attempts
        self._clock = clock
        with self._lock:
            self._connection.execute("PRAGMA journal_mode=WAL")
            self._connection.execute(
                """
                CREATE TABLE IF NOT EXISTS pending_events (
                    source_event_id TEXT PRIMARY KEY,
                    event_json TEXT NOT NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    next_attempt_at REAL NOT NULL DEFAULT 0,
                    last_error TEXT NULL,
                    created_at REAL NOT NULL,
                    claim_token TEXT NULL,
                    claim_until REAL NOT NULL DEFAULT 0
                )
                """
            )
            self._add_missing_claim_columns()
            self._connection.execute(
                """
                CREATE TABLE IF NOT EXISTS dead_letters (
                    source_event_id TEXT PRIMARY KEY,
                    event_json TEXT NOT NULL,
                    attempts INTEGER NOT NULL,
                    last_error TEXT,
                    created_at REAL NOT NULL,
                    dead_lettered_at REAL NOT NULL
                )
                """
            )
            self._connection.commit()

    def _add_missing_claim_columns(self) -> None:
        columns = {
            row[1] for row in self._connection.execute("PRAGMA table_info(pending_events)").fetchall()
        }
        if "claim_token" not in columns:
            self._connection.execute("ALTER TABLE pending_events ADD COLUMN claim_token TEXT NULL")
        if "claim_until" not in columns:
            self._connection.execute(
                "ALTER TABLE pending_events ADD COLUMN claim_until REAL NOT NULL DEFAULT 0"
            )

    def enqueue(self, event: dict) -> None:
        source_event_id = event["source_event_id"]
        with self._lock:
            self._connection.execute(
                """
                INSERT OR IGNORE INTO pending_events
                    (source_event_id, event_json, created_at)
                VALUES (?, ?, ?)
                """,
                (source_event_id, json.dumps(event, separators=(",", ":")), self._clock()),
            )
            self._connection.commit()

    def now(self) -> float:
        """Return the spool's current time source for coordinated components."""
        return self._clock()

    def claim_due(self, limit: int) -> list[PendingEvent]:
        with self._lock:
            now = self._clock()
            claim_token = str(uuid4())
            self._connection.execute("BEGIN IMMEDIATE")
            try:
                rows = self._connection.execute(
                    """
                    SELECT source_event_id, event_json, attempts, next_attempt_at
                    FROM pending_events
                    WHERE next_attempt_at <= ? AND claim_until <= ?
                    ORDER BY created_at, source_event_id
                    LIMIT ?
                    """,
                    (now, now, limit),
                ).fetchall()
                self._connection.executemany(
                    """
                    UPDATE pending_events
                    SET claim_token = ?, claim_until = ?
                    WHERE source_event_id = ? AND claim_until <= ?
                    """,
                    [
                        (claim_token, now + self._lease_seconds, row[0], now)
                        for row in rows
                    ],
                )
                self._connection.commit()
            except Exception:
                self._connection.rollback()
                raise
        return [
            PendingEvent(
                source_event_id=row[0],
                event=json.loads(row[1]),
                attempts=row[2],
                next_attempt_at=row[3],
                claim_token=claim_token,
            )
            for row in rows
        ]

    def mark_delivered(self, source_event_id: str, *, claim_token: str | None = None) -> None:
        with self._lock:
            if claim_token is None:
                self._connection.execute(
                    "DELETE FROM pending_events WHERE source_event_id = ? AND claim_token IS NULL",
                    (source_event_id,),
                )
            else:
                self._connection.execute(
                    "DELETE FROM pending_events WHERE source_event_id = ? AND claim_token = ?",
                    (source_event_id, claim_token),
                )
            self._connection.commit()

    def mark_failed(
        self,
        source_event_id: str,
        error: str,
        next_attempt_at: float,
        *,
        claim_token: str | None = None,
    ) -> bool:
        """Mark event as failed. Returns True if event is now dead-lettered (exceeded max attempts)."""
        with self._lock:
            where = "source_event_id = ?"
            params: list = [source_event_id]
            if claim_token is not None:
                where += " AND claim_token = ?"
                params.append(claim_token)
            else:
                where += " AND claim_token IS NULL"

            self._connection.execute(
                f"""
                UPDATE pending_events
                SET attempts = attempts + 1, last_error = ?, next_attempt_at = ?,
                    claim_token = NULL, claim_until = 0
                WHERE {where}
                """,
                [error, next_attempt_at, *params],
            )

            row = self._connection.execute(
                "SELECT attempts FROM pending_events WHERE source_event_id = ?",
                (source_event_id,),
            ).fetchone()

            if row is not None and row[0] >= self._max_attempts:
                self._dead_letter(source_event_id)
                self._connection.commit()
                return True

            self._connection.commit()
            return False

    def _dead_letter(self, source_event_id: str) -> None:
        """Move an exhausted event to the dead_letter table for later inspection."""
        now = self._clock()
        self._connection.execute(
            """
            INSERT OR IGNORE INTO dead_letters
                (source_event_id, event_json, attempts, last_error, created_at, dead_lettered_at)
            SELECT source_event_id, event_json, attempts, last_error, created_at, ?
            FROM pending_events
            WHERE source_event_id = ?
            """,
            (now, source_event_id),
        )
        self._connection.execute(
            "DELETE FROM pending_events WHERE source_event_id = ?",
            (source_event_id,),
        )

    def dead_letters(self) -> list[dict]:
        """Return all dead-lettered events for inspection."""
        with self._lock:
            rows = self._connection.execute(
                "SELECT source_event_id, event_json, attempts, last_error, created_at, dead_lettered_at "
                "FROM dead_letters ORDER BY dead_lettered_at",
            ).fetchall()
        return [
            {
                "source_event_id": r[0],
                "event": json.loads(r[1]),
                "attempts": r[2],
                "last_error": r[3],
                "created_at": r[4],
                "dead_lettered_at": r[5],
            }
            for r in rows
        ]

    def close(self) -> None:
        with self._lock:
            self._connection.close()
