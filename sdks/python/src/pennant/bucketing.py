"""Deterministic bucketing for percentage rollouts.

Mirrors the Pennant server's App\\Services\\Bucketing class byte-for-byte.
The cross-implementation drift corpus at ``tests/corpus/rules.json`` (repo
root) is the contract.
"""

from __future__ import annotations

import hashlib


def bucket(flag_key: str, identifier: str, seed: str) -> float:
    """Return a float in [0, 1) that's deterministic on (flag_key, identifier, seed)."""
    digest = hashlib.sha256(f"{flag_key}:{identifier}:{seed}".encode("utf-8")).hexdigest()
    return int(digest[:8], 16) / 0xFFFFFFFF


def is_in_rollout(flag_key: str, identifier: str, seed: str, percentage: float) -> bool:
    if percentage <= 0:
        return False
    if percentage >= 100:
        return True
    return bucket(flag_key, identifier, seed) < percentage / 100
