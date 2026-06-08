<?php

declare(strict_types=1);

namespace Pennant;

/**
 * Pennant's JSON expression tree evaluator — mirror of the server's
 * App\Services\RuleEvaluator. The cross-implementation drift corpus at
 * tests/corpus.json is the contract.
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
        if ($depth > 32) return false;

        if (array_key_exists('all', $expression)) {
            $children = $expression['all'];
            if (! is_array($children) || $children === []) return false;
            foreach ($children as $child) {
                if (! $this->evaluate((array) $child, $context, $depth + 1)) return false;
            }
            return true;
        }
        if (array_key_exists('any', $expression)) {
            $children = $expression['any'];
            if (! is_array($children) || $children === []) return false;
            foreach ($children as $child) {
                if ($this->evaluate((array) $child, $context, $depth + 1)) return true;
            }
            return false;
        }
        if (array_key_exists('none', $expression)) {
            $children = $expression['none'];
            if (! is_array($children) || $children === []) return true;
            foreach ($children as $child) {
                if ($this->evaluate((array) $child, $context, $depth + 1)) return false;
            }
            return true;
        }
        if (array_key_exists('segment', $expression)) {
            $key = $expression['segment'];
            if (! is_string($key)) return false;
            $segment = ($this->segmentResolver)($key);
            if ($segment === null) return false;
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
        if (! isset($expr['attribute'], $expr['op']) || ! array_key_exists('value', $expr)) return false;

        $actual = $this->lookup($context, $expr['attribute']);
        $expected = $expr['value'];

        return match ($expr['op']) {
            'equals' => $this->valuesEqual($actual, $expected),
            'not_equals' => ! $this->valuesEqual($actual, $expected),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'not_contains' => is_string($actual) && is_string($expected) && ! str_contains($actual, $expected),
            'starts_with' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'ends_with' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            'regex' => is_string($actual) && is_string($expected) && @preg_match('/'.str_replace('/', '\\/', $expected).'/', $actual) === 1,
            'gt' => $this->isNum($actual) && $this->isNum($expected) && $actual + 0 > $expected + 0,
            'gte' => $this->isNum($actual) && $this->isNum($expected) && $actual + 0 >= $expected + 0,
            'lt' => $this->isNum($actual) && $this->isNum($expected) && $actual + 0 < $expected + 0,
            'lte' => $this->isNum($actual) && $this->isNum($expected) && $actual + 0 <= $expected + 0,
            'before' => $this->dateCmp($actual, $expected, -1),
            'after' => $this->dateCmp($actual, $expected, 1),
            'percentage' => $this->matchPercentage($context, $expected),
            default => false,
        };
    }

    /** @param array<string, mixed> $context */
    private function lookup(array $context, string $attribute): mixed
    {
        if (! str_contains($attribute, '.')) return $context[$attribute] ?? null;
        $cursor = $context;
        foreach (explode('.', $attribute) as $part) {
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) return null;
            $cursor = $cursor[$part];
        }
        return $cursor;
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($this->isNum($a) && $this->isNum($b)) return ($a + 0) == ($b + 0);
        return $a === $b;
    }

    private function isNum(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }

    private function dateCmp(mixed $a, mixed $b, int $direction): bool
    {
        if (! is_string($a) || ! is_string($b)) return false;
        $aT = strtotime($a); $bT = strtotime($b);
        if ($aT === false || $bT === false) return false;
        return $direction < 0 ? $aT < $bT : $aT > $bT;
    }

    /** @param array<string, mixed> $context */
    private function matchPercentage(array $context, mixed $percentage): bool
    {
        if (! $this->isNum($percentage)) return false;
        if ($this->flagKey === null || $this->bucketingAttribute === null || $this->bucketingSeed === null) return false;
        $id = $this->lookup($context, $this->bucketingAttribute);
        if (! is_string($id) && ! is_int($id)) return false;
        return Bucketing::isInRollout($this->flagKey, (string) $id, $this->bucketingSeed, (float) $percentage);
    }
}
