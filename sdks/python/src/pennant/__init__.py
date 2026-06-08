"""Pennant SDK for Python."""

from .bucketing import bucket, is_in_rollout
from .client import Pennant
from .evaluator import evaluate_expression, evaluate_flag

__all__ = [
    "Pennant",
    "bucket",
    "is_in_rollout",
    "evaluate_expression",
    "evaluate_flag",
]

__version__ = "0.1.0"
