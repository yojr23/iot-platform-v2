from __future__ import annotations

import logging
import threading
import time

from app.backend_client import BackendClient, BackendClientError
from app.spool import DurableEventSpool

logger = logging.getLogger(__name__)


def retry_delay(*, attempt: int, base: float, maximum: float) -> float:
    return min(maximum, base * (2 ** max(0, attempt - 1)))


class DeliveryWorker:
    def __init__(
        self,
        spool: DurableEventSpool,
        backend_client: BackendClient,
        *,
        retry_base_seconds: float,
        retry_max_seconds: float,
        batch_size: int,
        idle_wait_seconds: float = 1,
    ) -> None:
        self._spool = spool
        self._backend_client = backend_client
        self._retry_base_seconds = retry_base_seconds
        self._retry_max_seconds = retry_max_seconds
        self._batch_size = batch_size
        self._idle_wait_seconds = idle_wait_seconds
        self._stop_event = threading.Event()

    def run_once(self) -> int:
        pending = self._spool.claim_due(self._batch_size)
        for item in pending:
            try:
                self._backend_client.send_raw_event(item.event)
            except BackendClientError as exc:
                delay = retry_delay(
                    attempt=item.attempts + 1,
                    base=self._retry_base_seconds,
                    maximum=self._retry_max_seconds,
                )
                self._spool.mark_failed(
                    item.source_event_id,
                    str(exc),
                    time.time() + delay,
                    claim_token=item.claim_token,
                )
                logger.warning(
                    "Backend delivery deferred source_event_id=%s attempt=%s delay=%ss error=%s",
                    item.source_event_id,
                    item.attempts + 1,
                    delay,
                    exc,
                )
            else:
                self._spool.mark_delivered(item.source_event_id, claim_token=item.claim_token)
                logger.info("Backend delivery completed source_event_id=%s", item.source_event_id)
        return len(pending)

    def run_forever(self) -> None:
        while not self._stop_event.is_set():
            self.run_once()
            self._stop_event.wait(self._idle_wait_seconds)

    def stop(self) -> None:
        self._stop_event.set()
