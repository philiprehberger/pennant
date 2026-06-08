<?php

namespace App\Services;

/**
 * Pennant's JSON expression tree evaluator.
 *
 * Expression forms:
 *   { attribute, op, value }   terminal comparison
 *   { segment }                segment reference
 *   { all: [...] }             AND
 *   { any: [...] }             OR
 *   { none: [...] }            NOT
 *
 * The companion TS / Python / PHP-SDK implementations mirror this file
 * line-for-line. The cross-implementation corpus at tests/corpus/rules.json
 * is what enforces semantic parity — change this evaluator only after
 * extending the corpus.
 *
 * Bucketing context for the `percentage` op is passed via the optional
 * fourth constructor argument (flagKey + bucketingAttribute + bucketingSeed).
 * If absent, percentage rules always fail (treated as "no bucketing
 * context available" rather than throwing — that matches SDK behaviour).
 */
final class RuleEvaluator
{
    /** @var callable(string): ?array{condition: array<string,mixed>} */
    private $segmentResolver;

    private ?string $flagKey = null;
    private ?string $bucketingAttribute = null;
    private ?string $bucketingSeed = null;

    /**
     * @param  callable(string): ?array{condition: array<string,mixed>}  $segmentResolver
     */
    public function __construct(callable $segmentResolver)
    {
        $this->segmentResolver = $segmentResolver;
    }

    public function withBucketing(string $flagKey, string $attribute, string $seed): self
    {
        $clone = clone $this;
        $clone->flagKey = $flagKey;
        $clone->bucketingAttribute = $attribute;
        $clone->bucketingSeed = $seed;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $expression
     * @param  array<string, mixed>  $context
     */
    public function evaluate(array $expression, array $context, int $depth = 0): bool
    {
        if ($depth > 32) {
            // Cycle / runaway nesting protection. Treat as no-match.
            return false;
        }

        if (array_key_exists('all', $expression)) {
            $children = $expression['all'];
            if (! is_array($children) || $children === []) {
                return false;
            }
            foreach ($children as $child) {
                if (! $this->evaluate((array) $child, $context, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $expression)) {
            $children = $expression['any'];
            if (! is_array($children) || $children === []) {
                return false;
            }
            foreach ($children as $child) {
                if ($this->evaluate((array) $child, $context, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists('none', $expression)) {
            $children = $expression['none'];
            if (! is_array($children) || $children === []) {
                return true;
            }
            foreach ($children as $child) {
                if ($this->evaluate((array) $child, $context, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('segment', $expression)) {
            $key = $expression['segment'];
            if (! is_string($key)) {
                return false;
            }
            $segment = ($this->segmentResolver)($key);
            if ($segment === null) {
                return false;
            }

            return $this->evaluate($segment['condition'], $context, $depth + 1);
        }

        return $this->evaluateTerminal($expression, $context);
    }

    /**
     * @param  array<string, mixed>  $expr
     * @param  array<string, mixed>  $context
     */
    private function evaluateTerminal(array $expr, array $context): bool
    {
        if (! isset($expr['attribute'], $expr['op']) || ! array_key_exists('value', $expr)) {
            return false;
        }

        $attribute = $expr['attribute'];
        $op = $expr['op'];
        $expected = $expr['value'];
        $actual = $this->lookup($context, $attribute);

        return match ($op) {
            'equals' => $this->valuesEqual($actual, $expected),
            'not_equals' => ! $this->valuesEqual($actual, $expected),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'not_contains' => is_string($actual) && is_string($expected) && ! str_contains($actual, $expected),
            'starts_with' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'ends_with' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            'regex' => is_string($actual) && is_string($expected) && @preg_match('/'.str_replace('/', '\\/', $expected).'/', $actual) === 1,
            'gt' => $this->isNumeric($actual) && $this->isNumeric($expected) && $actual + 0 > $expected + 0,
            'gte' => $this->isNumeric($actual) && $this->isNumeric($expected) && $actual + 0 >= $expected + 0,
            'lt' => $this->isNumeric($actual) && $this->isNumeric($expected) && $actual + 0 < $expected + 0,
            'lte' => $this->isNumeric($actual) && $this->isNumeric($expected) && $actual + 0 <= $expected + 0,
            'before' => $this->dateBefore($actual, $expected),
            'after' => $this->dateAfter($actual, $expected),
            'percentage' => $this->matchPercentage($context, $expected),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function lookup(array $context, string $attribute): mixed
    {
        // Dotted attribute path support: "address.country" → $context['address']['country'].
        if (str_contains($attribute, '.')) {
            $parts = explode('.', $attribute);
            $cursor = $context;
            foreach ($parts as $part) {
                if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                    return null;
                }
                $cursor = $cursor[$part];
            }

            return $cursor;
        }

        return $context[$attribute] ?? null;
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        // Numeric-equality across int / float.
        if ($this->isNumeric($a) && $this->isNumeric($b)) {
            return ($a + 0) == ($b + 0);
        }

        return $a === $b;
    }

    private function isNumeric(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }

    private function dateBefore(mixed $actual, mixed $expected): bool
    {
        $a = $this->parseDate($actual);
        $b = $this->parseDate($expected);

        return $a !== null && $b !== null && $a < $b;
    }

    private function dateAfter(mixed $actual, mixed $expected): bool
    {
        $a = $this->parseDate($actual);
        $b = $this->parseDate($expected);

        return $a !== null && $b !== null && $a > $b;
    }

    private function parseDate(mixed $v): ?int
    {
        if (! is_string($v) || $v === '') {
            return null;
        }
        $ts = strtotime($v);

        return $ts === false ? null : $ts;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchPercentage(array $context, mixed $percentage): bool
    {
        if (! $this->isNumeric($percentage)) {
            return false;
        }
        if ($this->flagKey === null || $this->bucketingAttribute === null || $this->bucketingSeed === null) {
            return false;
        }
        $identifier = $this->lookup($context, $this->bucketingAttribute);
        if (! is_string($identifier) && ! is_int($identifier)) {
            return false;
        }

        return Bucketing::isInRollout(
            flagKey: $this->flagKey,
            identifier: (string) $identifier,
            seed: $this->bucketingSeed,
            percentage: (float) $percentage,
        );
    }
}
