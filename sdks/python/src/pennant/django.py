"""Django middleware that attaches a per-request Pennant client to ``request.pennant``.

Configure via ``settings.py``:

.. code-block:: python

    PENNANT = {
        "API_BASE": "https://api.pennant.philiprehberger.com",
        "API_KEY": os.environ["PENNANT_API_KEY"],
        "ENVIRONMENT": "prod",
    }

    MIDDLEWARE = [
        ...
        "pennant.django.PennantMiddleware",
    ]

Then read flags in views:

.. code-block:: python

    def my_view(request):
        if request.pennant.bool("new-checkout-flow", False):
            ...

The middleware also offers a ``flag_required`` decorator:

.. code-block:: python

    from pennant.django import flag_required

    @flag_required("admin-panel")
    def admin_view(request):
        ...
"""

from __future__ import annotations

from functools import wraps
from typing import Any, Callable

from .client import Pennant


class PennantMiddleware:
    def __init__(self, get_response: Callable[..., Any]) -> None:
        from django.conf import settings  # type: ignore[import-not-found]

        cfg = getattr(settings, "PENNANT", {})
        self._client = Pennant(
            api_base=cfg["API_BASE"],
            api_key=cfg["API_KEY"],
            environment=cfg.get("ENVIRONMENT", "prod"),
            context=cfg.get("CONTEXT", {}),
            refresh_interval=int(cfg.get("REFRESH_INTERVAL", 30)),
        )
        self._get_response = get_response

    def __call__(self, request: Any) -> Any:
        # Reuse one client per process; per-request context overrides go via
        # request.pennant.evaluate(key, context_override=...).
        request.pennant = self._client
        return self._get_response(request)


def flag_required(flag_key: str, fallback: bool = False):
    """View decorator that returns 404 unless the flag is on for the request."""

    def decorator(view_func: Callable[..., Any]) -> Callable[..., Any]:
        @wraps(view_func)
        def _wrapped(request: Any, *args: Any, **kwargs: Any) -> Any:
            from django.http import Http404  # type: ignore[import-not-found]

            client: Pennant | None = getattr(request, "pennant", None)
            if client is None or not client.bool(flag_key, fallback):
                raise Http404(f"Flag '{flag_key}' is not enabled.")
            return view_func(request, *args, **kwargs)

        return _wrapped

    return decorator
