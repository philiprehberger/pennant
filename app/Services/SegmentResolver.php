<?php

namespace App\Services;

use App\Models\Segment;
use App\Models\Workspace;

/**
 * Lazy segment lookup with cycle detection.
 *
 * Stored as a callable in RuleEvaluator. First call for a segment fires a DB
 * query; subsequent calls within the same evaluator are served from memory.
 * Cycles are caught at evaluation time by the depth limit in RuleEvaluator
 * but we also pre-validate on segment write (see SegmentsController).
 */
final class SegmentResolver
{
    /** @var array<string, ?array{condition: array<string,mixed>}> */
    private array $cache = [];

    public function __construct(private readonly Workspace $workspace)
    {
    }

    public function resolve(string $key): ?array
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        /** @var Segment|null $segment */
        $segment = $this->workspace->segments()->where('key', $key)->first();

        return $this->cache[$key] = $segment
            ? ['condition' => $segment->condition]
            : null;
    }

    public function asCallable(): callable
    {
        return fn (string $key) => $this->resolve($key);
    }

    /**
     * Static-analyse a candidate segment graph for cycles before saving.
     *
     * @param  array<string,mixed>  $candidateCondition
     * @return list<string>  the chain of segment keys that loops back, empty if no cycle.
     */
    public function detectCycle(string $candidateKey, array $candidateCondition): array
    {
        $existing = $this->workspace->segments()->get()->keyBy('key');
        $visiting = [];

        $walk = function (string $key, array $condition) use (&$walk, &$visiting, $existing, $candidateKey, $candidateCondition): ?array {
            if (in_array($key, $visiting, true)) {
                $visiting[] = $key;

                return $visiting;
            }
            $visiting[] = $key;

            $cycle = $this->walkExpression($condition, function (string $referenced) use (&$walk, $existing, $candidateKey, $candidateCondition) {
                if ($referenced === $candidateKey) {
                    return $walk($candidateKey, $candidateCondition);
                }
                $seg = $existing->get($referenced);

                return $seg === null ? null : $walk($referenced, $seg->condition);
            });

            array_pop($visiting);

            return $cycle;
        };

        return $walk($candidateKey, $candidateCondition) ?? [];
    }

    /**
     * @param  array<string,mixed>  $expression
     * @param  callable(string): ?list<string>  $visit  Called for each segment ref; returning a non-null
     *                                                  list halts traversal with that cycle chain.
     * @return ?list<string>
     */
    private function walkExpression(array $expression, callable $visit): ?array
    {
        if (isset($expression['segment']) && is_string($expression['segment'])) {
            return $visit($expression['segment']);
        }
        foreach (['all', 'any', 'none'] as $combiner) {
            if (! isset($expression[$combiner]) || ! is_array($expression[$combiner])) {
                continue;
            }
            foreach ($expression[$combiner] as $child) {
                if (! is_array($child)) {
                    continue;
                }
                $hit = $this->walkExpression($child, $visit);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        return null;
    }
}
