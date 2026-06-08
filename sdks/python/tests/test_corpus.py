"""Cross-implementation drift corpus — runs the same fixtures as the Laravel
server (PHPUnit) and the TypeScript SDK (Vitest). Drift here fails CI."""

from __future__ import annotations

import json
import math
from pathlib import Path

import pytest

from pennant.bucketing import bucket
from pennant.evaluator import evaluate_expression

CORPUS_PATH = Path(__file__).resolve().parents[3] / "tests" / "corpus" / "rules.json"


def _load_corpus() -> dict:
    return json.loads(CORPUS_PATH.read_text(encoding="utf-8"))


CORPUS = _load_corpus()


@pytest.mark.parametrize("case", CORPUS["cases"], ids=lambda c: c["name"])
def test_rule_engine_corpus(case: dict) -> None:
    segments = CORPUS["segments"]

    def resolve(key: str):
        return {"condition": segments[key]} if key in segments else None

    actual = evaluate_expression(case["condition"], case["context"], resolve)
    assert actual == case["expected"], f"Corpus drift on {case['name']!r}"


@pytest.mark.parametrize(
    "case",
    CORPUS["bucketing"]["cases"],
    ids=lambda c: f"{c['flagKey']}/{c['identifier']}/{c['seed']}",
)
def test_bucketing_corpus(case: dict) -> None:
    actual = bucket(case["flagKey"], case["identifier"], case["seed"])
    assert math.isclose(actual, case["expected"], abs_tol=1e-10), (
        f"Bucketing drift: expected {case['expected']} got {actual}"
    )
