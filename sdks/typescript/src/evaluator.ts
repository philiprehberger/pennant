import { isInRollout } from './bucketing.js';
import type {
  EvaluationContext,
  FlagConfiguration,
  FlagDefinition,
  RuleExpression,
  SegmentDefinition,
  EvaluationResult,
} from './types.js';

/**
 * Pennant's JSON expression tree evaluator. Mirrors the PHP server
 * (App\Services\RuleEvaluator) line-for-line. Drift between this file and
 * its PHP twin corrupts experiment data silently — the drift corpus at
 * `tests/corpus/rules.json` at repo root is the contract; any rule
 * semantics change has to land in the corpus first and then in every
 * implementation.
 */
export type SegmentResolver = (key: string) => SegmentDefinition | null;

export interface BucketingContext {
  flagKey: string;
  bucketingAttribute: string;
  bucketingSeed: string;
}

export function evaluateExpression(
  expression: RuleExpression,
  context: EvaluationContext,
  resolveSegment: SegmentResolver,
  bucketing?: BucketingContext,
  depth = 0,
): boolean {
  if (depth > 32) return false;

  if (isAll(expression)) {
    if (expression.all.length === 0) return false;
    return expression.all.every((child) =>
      evaluateExpression(child, context, resolveSegment, bucketing, depth + 1),
    );
  }
  if (isAny(expression)) {
    if (expression.any.length === 0) return false;
    return expression.any.some((child) =>
      evaluateExpression(child, context, resolveSegment, bucketing, depth + 1),
    );
  }
  if (isNone(expression)) {
    if (expression.none.length === 0) return true;
    return !expression.none.some((child) =>
      evaluateExpression(child, context, resolveSegment, bucketing, depth + 1),
    );
  }
  if (isSegment(expression)) {
    const seg = resolveSegment(expression.segment);
    if (!seg) return false;
    return evaluateExpression(seg.condition, context, resolveSegment, bucketing, depth + 1);
  }

  return evaluateTerminal(expression, context, bucketing);
}

function evaluateTerminal(
  expr: Extract<RuleExpression, { attribute: string }>,
  context: EvaluationContext,
  bucketing?: BucketingContext,
): boolean {
  const actual = lookup(context, expr.attribute);
  const expected = expr.value;

  switch (expr.op) {
    case 'equals':
      return valuesEqual(actual, expected);
    case 'not_equals':
      return !valuesEqual(actual, expected);
    case 'in':
      return Array.isArray(expected) && expected.includes(actual as never);
    case 'not_in':
      return Array.isArray(expected) && !expected.includes(actual as never);
    case 'contains':
      return typeof actual === 'string' && typeof expected === 'string' && actual.includes(expected);
    case 'not_contains':
      return typeof actual === 'string' && typeof expected === 'string' && !actual.includes(expected);
    case 'starts_with':
      return typeof actual === 'string' && typeof expected === 'string' && actual.startsWith(expected);
    case 'ends_with':
      return typeof actual === 'string' && typeof expected === 'string' && actual.endsWith(expected);
    case 'regex':
      return typeof actual === 'string' && typeof expected === 'string' && safeRegex(expected).test(actual);
    case 'gt':
      return isNum(actual) && isNum(expected) && actual > expected;
    case 'gte':
      return isNum(actual) && isNum(expected) && actual >= expected;
    case 'lt':
      return isNum(actual) && isNum(expected) && actual < expected;
    case 'lte':
      return isNum(actual) && isNum(expected) && actual <= expected;
    case 'before':
      return dateCompare(actual, expected, -1);
    case 'after':
      return dateCompare(actual, expected, 1);
    case 'percentage':
      return matchPercentage(context, expected, bucketing);
    default:
      return false;
  }
}

function lookup(context: EvaluationContext, attribute: string): unknown {
  if (!attribute.includes('.')) return context[attribute];
  const parts = attribute.split('.');
  let cursor: unknown = context;
  for (const part of parts) {
    if (cursor === null || cursor === undefined || typeof cursor !== 'object') return null;
    cursor = (cursor as Record<string, unknown>)[part];
  }
  return cursor;
}

function valuesEqual(a: unknown, b: unknown): boolean {
  if (isNum(a) && isNum(b)) return a === b;
  return a === b;
}

function isNum(v: unknown): v is number {
  return typeof v === 'number' && !Number.isNaN(v);
}

function dateCompare(a: unknown, b: unknown, direction: -1 | 1): boolean {
  if (typeof a !== 'string' || typeof b !== 'string') return false;
  const aT = Date.parse(a);
  const bT = Date.parse(b);
  if (Number.isNaN(aT) || Number.isNaN(bT)) return false;
  return direction === -1 ? aT < bT : aT > bT;
}

function safeRegex(pattern: string): RegExp {
  try {
    return new RegExp(pattern);
  } catch {
    return /(?!)/;
  }
}

function matchPercentage(
  context: EvaluationContext,
  percentage: unknown,
  bucketing?: BucketingContext,
): boolean {
  if (!isNum(percentage) || !bucketing) return false;
  const id = lookup(context, bucketing.bucketingAttribute);
  if (typeof id !== 'string' && typeof id !== 'number') return false;
  return isInRollout(bucketing.flagKey, String(id), bucketing.bucketingSeed, percentage);
}

function isAll(e: RuleExpression): e is { all: RuleExpression[] } {
  return 'all' in (e as object);
}
function isAny(e: RuleExpression): e is { any: RuleExpression[] } {
  return 'any' in (e as object);
}
function isNone(e: RuleExpression): e is { none: RuleExpression[] } {
  return 'none' in (e as object);
}
function isSegment(e: RuleExpression): e is { segment: string } {
  return 'segment' in (e as object);
}

/**
 * Top-level flag evaluation: given a flag definition + context, return the
 * resolved value, reason, and matched rule index. Mirrors
 * App\Services\FlagEvaluator on the server.
 */
export function evaluateFlag(
  flag: FlagDefinition,
  context: EvaluationContext,
  resolveSegment: SegmentResolver,
): EvaluationResult {
  const config: FlagConfiguration | null | undefined = flag.configuration;

  if (!config) {
    return { value: flag.default_value, reason: 'default', ruleIndex: null };
  }
  if (config.state === 'off') {
    return { value: flag.default_value, reason: 'off', ruleIndex: null };
  }

  const bucketing: BucketingContext = {
    flagKey: flag.key,
    bucketingAttribute: config.bucketing_attribute,
    bucketingSeed: config.bucketing_seed,
  };

  let idx = 0;
  for (const rule of config.rules) {
    if (evaluateExpression(rule.condition, context, resolveSegment, bucketing)) {
      const reason = JSON.stringify(rule.condition).includes('"percentage"')
        ? 'percentage_rollout'
        : 'rule_match';
      return { value: rule.variation, reason, ruleIndex: idx };
    }
    idx += 1;
  }

  return { value: config.variation, reason: 'fallthrough', ruleIndex: null };
}
