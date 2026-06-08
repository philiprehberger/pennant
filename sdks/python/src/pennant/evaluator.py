"""Pennant rule-engine evaluator.

Mirrors ``App\\Services\\RuleEvaluator`` on the server. Drift between
implementations corrupts experiment data silently — the cross-implementation
corpus at tests/corpus/rules.json (repo root) is the contract.
"""

from __future__ import annotations

import json
import re
from datetime import datetime
from typing import Any, Callable, Mapping, Sequence

from .bucketing import is_in_rollout

SegmentResolver = Callable[[str], "dict[str, Any] | None"]


def _lookup(context: Mapping[str, Any], attribute: str) -> Any:
    if "." not in attribute:
        return context.get(attribute)
    cursor: Any = context
    for part in attribute.split("."):
        if not isinstance(cursor, Mapping) or part not in cursor:
            return None
        cursor = cursor[part]
    return cursor


def _is_num(v: Any) -> bool:
    return isinstance(v, (int, float)) and not isinstance(v, bool)


def _values_equal(a: Any, b: Any) -> bool:
    if _is_num(a) and _is_num(b):
        return a == b
    return a == b


def _parse_date(v: Any) -> datetime | None:
    if not isinstance(v, str) or not v:
        return None
    try:
        # Most ISO-8601-ish strings; broad coverage matches the PHP server.
        return datetime.fromisoformat(v.replace("Z", "+00:00"))
    except Exception:
        return None


def _date_cmp(a: Any, b: Any, direction: int) -> bool:
    aT = _parse_date(a)
    bT = _parse_date(b)
    if aT is None or bT is None:
        return False
    return (aT < bT) if direction < 0 else (aT > bT)


def _safe_regex(pattern: str) -> re.Pattern[str] | None:
    try:
        return re.compile(pattern)
    except re.error:
        return None


def _match_percentage(
    context: Mapping[str, Any],
    expected: Any,
    bucketing: tuple[str, str, str] | None,
) -> bool:
    if not _is_num(expected) or bucketing is None:
        return False
    flag_key, attribute, seed = bucketing
    ident = _lookup(context, attribute)
    if not isinstance(ident, (str, int)) or isinstance(ident, bool):
        return False
    return is_in_rollout(flag_key, str(ident), seed, float(expected))


def evaluate_expression(
    expression: Mapping[str, Any],
    context: Mapping[str, Any],
    resolve_segment: SegmentResolver,
    bucketing: tuple[str, str, str] | None = None,
    depth: int = 0,
) -> bool:
    if depth > 32:
        return False

    if "all" in expression:
        children = expression["all"]
        if not isinstance(children, Sequence) or len(children) == 0:
            return False
        return all(
            evaluate_expression(c, context, resolve_segment, bucketing, depth + 1)
            for c in children
        )
    if "any" in expression:
        children = expression["any"]
        if not isinstance(children, Sequence) or len(children) == 0:
            return False
        return any(
            evaluate_expression(c, context, resolve_segment, bucketing, depth + 1)
            for c in children
        )
    if "none" in expression:
        children = expression["none"]
        if not isinstance(children, Sequence) or len(children) == 0:
            return True
        return not any(
            evaluate_expression(c, context, resolve_segment, bucketing, depth + 1)
            for c in children
        )
    if "segment" in expression:
        key = expression["segment"]
        if not isinstance(key, str):
            return False
        seg = resolve_segment(key)
        if seg is None:
            return False
        return evaluate_expression(seg["condition"], context, resolve_segment, bucketing, depth + 1)

    return _evaluate_terminal(expression, context, bucketing)


def _evaluate_terminal(
    expr: Mapping[str, Any],
    context: Mapping[str, Any],
    bucketing: tuple[str, str, str] | None,
) -> bool:
    if "attribute" not in expr or "op" not in expr or "value" not in expr:
        return False
    actual = _lookup(context, expr["attribute"])
    expected = expr["value"]
    op = expr["op"]

    if op == "equals":
        return _values_equal(actual, expected)
    if op == "not_equals":
        return not _values_equal(actual, expected)
    if op == "in":
        return isinstance(expected, Sequence) and not isinstance(expected, str) and actual in expected
    if op == "not_in":
        return isinstance(expected, Sequence) and not isinstance(expected, str) and actual not in expected
    if op == "contains":
        return isinstance(actual, str) and isinstance(expected, str) and expected in actual
    if op == "not_contains":
        return isinstance(actual, str) and isinstance(expected, str) and expected not in actual
    if op == "starts_with":
        return isinstance(actual, str) and isinstance(expected, str) and actual.startswith(expected)
    if op == "ends_with":
        return isinstance(actual, str) and isinstance(expected, str) and actual.endswith(expected)
    if op == "regex":
        if not isinstance(actual, str) or not isinstance(expected, str):
            return False
        pat = _safe_regex(expected)
        return pat is not None and pat.search(actual) is not None
    if op == "gt":
        return _is_num(actual) and _is_num(expected) and actual > expected
    if op == "gte":
        return _is_num(actual) and _is_num(expected) and actual >= expected
    if op == "lt":
        return _is_num(actual) and _is_num(expected) and actual < expected
    if op == "lte":
        return _is_num(actual) and _is_num(expected) and actual <= expected
    if op == "before":
        return _date_cmp(actual, expected, -1)
    if op == "after":
        return _date_cmp(actual, expected, 1)
    if op == "percentage":
        return _match_percentage(context, expected, bucketing)
    return False


def evaluate_flag(
    flag: Mapping[str, Any],
    context: Mapping[str, Any],
    resolve_segment: SegmentResolver,
) -> dict[str, Any]:
    config = flag.get("configuration")
    default = flag.get("default_value")

    if not isinstance(config, Mapping):
        return {"value": default, "reason": "default", "rule_index": None}
    if config.get("state", "off") == "off":
        return {"value": default, "reason": "off", "rule_index": None}

    bucketing = (
        flag.get("key", ""),
        config.get("bucketing_attribute", "userId"),
        config.get("bucketing_seed", ""),
    )

    for idx, rule in enumerate(config.get("rules") or []):
        condition = rule.get("condition") or {}
        if evaluate_expression(condition, context, resolve_segment, bucketing):
            reason = (
                "percentage_rollout"
                if '"percentage"' in json.dumps(condition)
                else "rule_match"
            )
            return {"value": rule.get("variation"), "reason": reason, "rule_index": idx}

    return {"value": config.get("variation"), "reason": "fallthrough", "rule_index": None}
