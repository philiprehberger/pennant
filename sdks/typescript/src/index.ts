export { Pennant } from './client.js';
export { bucket, isInRollout } from './bucketing.js';
export { evaluateExpression, evaluateFlag } from './evaluator.js';
export type {
  EvaluationContext,
  EvaluationReason,
  EvaluationResult,
  FlagConfiguration,
  FlagDefinition,
  FlagType,
  FlagValue,
  LifecycleEvent,
  LifecycleHandler,
  PennantOptions,
  RuleExpression,
  SegmentDefinition,
  Snapshot,
  TargetingRule,
  TerminalRule,
  SegmentRule,
  AllRule,
  AnyRule,
  NoneRule,
} from './types.js';
