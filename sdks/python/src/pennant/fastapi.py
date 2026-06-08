"""FastAPI dependency for Pennant.

.. code-block:: python

    from fastapi import FastAPI, Depends
    from pennant.fastapi import pennant_dependency, flag_required

    app = FastAPI()

    @app.get("/")
    async def root(pennant=Depends(pennant_dependency(API_BASE, API_KEY, "prod"))):
        if pennant.bool("new-checkout-flow", False):
            return {"checkout": "new"}
        return {"checkout": "legacy"}

    @app.get("/admin", dependencies=[Depends(flag_required("admin-panel"))])
    async def admin():
        ...
"""

from __future__ import annotations

from typing import Any, Callable, Mapping

from .client import Pennant


def pennant_dependency(
    api_base: str,
    api_key: str,
    environment: str,
    *,
    context: Mapping[str, Any] | None = None,
    refresh_interval: int = 30,
) -> Callable[..., Pennant]:
    """Returns a FastAPI-compatible dependency that yields a singleton Pennant client."""
    client = Pennant(
        api_base=api_base,
        api_key=api_key,
        environment=environment,
        context=context,
        refresh_interval=refresh_interval,
    )

    def _dep() -> Pennant:
        return client

    return _dep


def flag_required(flag_key: str, fallback: bool = False) -> Callable[..., None]:
    """FastAPI dependency that raises 404 if the flag isn't on."""

    def _dep(pennant_client: Pennant) -> None:  # type: ignore[valid-type]
        from fastapi import HTTPException  # type: ignore[import-not-found]

        if not pennant_client.bool(flag_key, fallback):
            raise HTTPException(status_code=404, detail=f"Flag '{flag_key}' is not enabled.")

    return _dep
