export type FlagType = 'bool' | 'string' | 'number' | 'json';

export type FlagValue = boolean | string | number | Record<string, unknown> | unknown[] | null;

export interface EvaluationContext {
  [key: string]: unknown;
}

export type RuleExpression =
  | TerminalRule
  | SegmentRule
  | AllRule
  | AnyRule
  | NoneRule;

export interface TerminalRule {
  attribute: string;
  op:
    | 'equals'
    | 'not_equals'
    | 'in'
    | 'not_in'
    | 'contains'
    | 'not_contains'
    | 'starts_with'
    | 'ends_with'
    | 'regex'
    | 'gt'
    | 'gte'
    | 'lt'
    | 'lte'
    | 'before'
    | 'after'
    | 'percentage';
  value: unknown;
}

export interface SegmentRule {
  segment: string;
}

export interface AllRule {
  all: RuleExpression[];
}

export interface AnyRule {
  any: RuleExpression[];
}

export interface NoneRule {
  none: RuleExpression[];
}

export interface TargetingRule {
  priority: number;
  condition: RuleExpression;
  variation: FlagValue;
}

export interface FlagConfiguration {
  state: 'on' | 'off';
  variation: FlagValue;
  bucketing_attribute: string;
  bucketing_seed: string;
  rules: TargetingRule[];
}

export interface FlagDefinition {
  key: string;
  type: FlagType;
  default_value: FlagValue;
  // Server snapshot: present.
  configuration?: FlagConfiguration | null;
  // Client snapshot: present.
  value?: FlagValue;
  reason?: EvaluationReason;
}

export interface SegmentDefinition {
  key: string;
  name?: string;
  condition: RuleExpression;
}

export interface Snapshot {
  environment: string;
  version: string;
  kind: 'server' | 'client';
  flags: FlagDefinition[];
  segments?: SegmentDefinition[];
}

export type EvaluationReason =
  | 'default'
  | 'off'
  | 'rule_match'
  | 'percentage_rollout'
  | 'fallthrough';

export interface EvaluationResult {
  value: FlagValue;
  reason: EvaluationReason;
  ruleIndex: number | null;
}

export interface PennantOptions {
  /** Server URL — e.g. https://api.pennant.philiprehberger.com */
  apiBase: string;
  /** Server or client API key. */
  apiKey: string;
  /** Environment key, e.g. "prod". */
  environment: string;
  /** Evaluation context (userId + attributes). */
  context?: EvaluationContext;
  /** Pre-bootstrapped snapshot (e.g. from server rendering). Skips first network call. */
  bootstrap?: Snapshot;
  /** Polling interval in ms when SSE is unavailable. Defaults to 30s. Set to 0 to disable polling. */
  pollIntervalMs?: number;
  /** Persistence key. Set to `null` to disable persistence entirely. */
  storageKey?: string | null;
  /** Optional fetch implementation override (test injection). */
  fetch?: typeof globalThis.fetch;
  /** Optional EventSource implementation override (test injection). Defaults to global EventSource if available. */
  EventSourceImpl?: typeof EventSource | null;
}

export type LifecycleEvent = 'ready' | 'update' | 'error' | 'connected' | 'disconnected';

export type LifecycleHandler<T = unknown> = (payload: T) => void;
