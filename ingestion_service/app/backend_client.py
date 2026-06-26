from __future__ import annotations

from typing import Any

import requests


class BackendClientError(RuntimeError):
    """Raised when backend ingestion endpoint cannot be reached or rejects payload."""


class BackendClient:
    def __init__(
        self,
        base_url: str,
        ingestion_token: str,
        *,
        timeout_seconds: int = 10,
        session: requests.sessions.Session | None = None,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._ingestion_token = ingestion_token
        self._timeout_seconds = timeout_seconds
        self._session = session or requests

    def send_raw_event(self, event: dict[str, Any]) -> dict[str, Any]:
        if not self._ingestion_token:
            raise BackendClientError("BACKEND_INGESTION_TOKEN is empty.")

        url = f"{self._base_url}/api/ingestion/events"
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-Ingestion-Token": self._ingestion_token,
        }

        try:
            response = self._session.post(
                url,
                json=event,
                headers=headers,
                timeout=self._timeout_seconds,
            )
        except requests.RequestException as exc:
            raise BackendClientError(f"Failed to reach backend endpoint: {exc}") from exc

        if response.status_code >= 400:
            raise BackendClientError(
                f"Backend rejected raw event with status {response.status_code}: {response.text[:300]}"
            )

        try:
            return response.json()
        except ValueError as exc:
            raise BackendClientError("Backend response is not valid JSON.") from exc
