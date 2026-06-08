"""Pennant Python client."""

from __future__ import annotations

import base64
import json
import time
from typing import Any, Callable, Mapping

from .evaluator import evaluate_flag


class Pennant:
    """Server-side feature flag client.

    .. code-block:: python

        from pennant import Pennant

        pennant = Pennant(
            api_base="https://api.pennant.philiprehberger.com",
            api_key=os.environ["PENNANT_KEY"],
            environment="prod",
            context={"userId": "alice", "plan": "enterprise"},
        )

        if pennant.bool("new-checkout-flow", False):
            ...

    Snapshots are fetched on first read (or eagerly via ``bootstrap``) and
    refreshed at ``refresh_interval`` seconds. Real-time push is via the SSE
    endpoint — wire a separate consumer if you need sub-second propagation.
    """

    def __init__(
        self,
        *,
        api_base: str,
        api_key: str,
        environment: str,
        context: Mapping[str, Any] | None = None,
        refresh_interval: int = 30,
        bootstrap: Mapping[str, Any] | None = None,
        http_get: Callable[[str, Mapping[str, str]], str | None] | None = None,
    ) -> None:
        self._api_base = api_base.rstrip("/")
        self._api_key = api_key
        self._environment = environment
        self._context = dict(context or {})
        self._refresh_interval = refresh_interval
        self._http_get = http_get
        self._snapshot: dict[str, Any] | None = None
        self._segments: dict[str, dict[str, Any]] = {}
        self._last_fetch_ts = 0
        if bootstrap is not None:
            self._apply_snapshot(dict(bootstrap))

    def bool(self, key: str, fallback: bool) -> bool:
        v = self.evaluate(key)["value"]
        return v if isinstance(v, bool) else fallback

    def string(self, key: str, fallback: str) -> str:
        v = self.evaluate(key)["value"]
        return v if isinstance(v, str) else fallback

    def number(self, key: str, fallback: float) -> float:
        v = self.evaluate(key)["value"]
        return float(v) if isinstance(v, (int, float)) and not isinstance(v, bool) else fallback

    def json(self, key: str, fallback: Any) -> Any:
        v = self.evaluate(key)["value"]
        return v if isinstance(v, (dict, list)) else fallback

    def evaluate(self, key: str, context_override: Mapping[str, Any] | None = None) -> dict[str, Any]:
        self._maybe_refresh()
        snap = self._snapshot
        if snap is None:
            return {"value": None, "reason": "default", "rule_index": None}

        flag = next((f for f in snap.get("flags", []) if f.get("key") == key), None)
        if flag is None:
            return {"value": None, "reason": "default", "rule_index": None}

        if snap.get("kind", "server") == "client":
            return {
                "value": flag.get("value"),
                "reason": flag.get("reason", "default"),
                "rule_index": None,
            }

        def resolver(seg_key: str) -> dict[str, Any] | None:
            seg = self._segments.get(seg_key)
            return None if seg is None else {"condition": seg["condition"]}

        return evaluate_flag(flag, context_override or self._context, resolver)

    def set_context(self, context: Mapping[str, Any]) -> None:
        self._context = dict(context)

    def refresh(self) -> None:
        url = f"{self._api_base}/v1/snapshot?environment={self._environment}"
        if self._api_key.startswith("pn_clt_"):
            ctx_b64 = base64.urlsafe_b64encode(json.dumps(self._context).encode("utf-8")).rstrip(b"=").decode("utf-8")
            url += f"&context={ctx_b64}"
        body = self._do_get(url)
        if body is None:
            return
        try:
            self._apply_snapshot(json.loads(body))
        except json.JSONDecodeError:
            return

    def _apply_snapshot(self, snap: dict[str, Any]) -> None:
        self._snapshot = snap
        self._segments = {seg["key"]: seg for seg in snap.get("segments", []) if "key" in seg}
        self._last_fetch_ts = int(time.time())

    def _maybe_refresh(self) -> None:
        if self._snapshot is None:
            self.refresh()
            return
        if time.time() - self._last_fetch_ts > self._refresh_interval:
            self.refresh()

    def _do_get(self, url: str) -> str | None:
        headers = {"Authorization": f"Bearer {self._api_key}", "Accept": "application/json"}
        if self._http_get is not None:
            return self._http_get(url, headers)
        try:
            import requests

            resp = requests.get(url, headers=headers, timeout=5)
            return resp.text if resp.status_code < 400 else None
        except Exception:
            return None
