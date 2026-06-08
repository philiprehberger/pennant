import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { bucket } from '../src/bucketing.js';
import { evaluateExpression } from '../src/evaluator.js';
import type { RuleExpression } from '../src/types.js';

const corpusPath = resolve(dirname(fileURLToPath(import.meta.url)), '../../../tests/corpus/rules.json');
const corpus = JSON.parse(readFileSync(corpusPath, 'utf-8'));

interface Case {
  name: string;
  condition: RuleExpression;
  context: Record<string, unknown>;
  expected: boolean;
}

interface BucketingCase {
  flagKey: string;
  identifier: string;
  seed: string;
  expected: number;
}

describe('rule-engine corpus (cross-implementation drift)', () => {
  const segments = corpus.segments as Record<string, RuleExpression>;
  const resolveSegment = (key: string) =>
    segments[key] ? { key, condition: segments[key] } : null;

  for (const c of corpus.cases as Case[]) {
    it(c.name, () => {
      expect(evaluateExpression(c.condition, c.context, resolveSegment)).toBe(c.expected);
    });
  }
});

describe('bucketing corpus (cross-implementation drift)', () => {
  for (const c of corpus.bucketing.cases as BucketingCase[]) {
    it(`${c.flagKey} / ${c.identifier} / ${c.seed}`, () => {
      expect(bucket(c.flagKey, c.identifier, c.seed)).toBeCloseTo(c.expected, 10);
    });
  }
});
