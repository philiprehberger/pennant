<?php

namespace Tests\Feature;

use App\Services\Bucketing;
use App\Services\RuleEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Round-trips tests/corpus/rules.json through the PHP rule evaluator + the
 * bucketing function. Every SDK runs the same corpus in its native test
 * runner; drift here = drift across implementations.
 *
 * Don't change rule semantics without first extending the corpus.
 */
class RuleEngineCorpusTest extends TestCase
{
    public function test_corpus_passes_through_php_evaluator(): void
    {
        $corpus = $this->loadCorpus();

        $segments = $corpus['segments'];
        $resolver = function (string $key) use ($segments): ?array {
            return isset($segments[$key]) ? ['condition' => $segments[$key]] : null;
        };
        $evaluator = new RuleEvaluator($resolver);

        foreach ($corpus['cases'] as $case) {
            $actual = $evaluator->evaluate($case['condition'], $case['context']);
            $this->assertSame(
                $case['expected'],
                $actual,
                "Corpus case '{$case['name']}' failed: expected ".var_export($case['expected'], true)." got ".var_export($actual, true),
            );
        }
    }

    public function test_bucketing_corpus_matches_implementation(): void
    {
        $corpus = $this->loadCorpus();

        foreach ($corpus['bucketing']['cases'] as $case) {
            $actual = Bucketing::bucket($case['flagKey'], $case['identifier'], $case['seed']);
            $this->assertEqualsWithDelta(
                $case['expected'],
                $actual,
                1e-10,
                "Bucketing drift for {$case['flagKey']}/{$case['identifier']}/{$case['seed']}: expected {$case['expected']} got {$actual}",
            );
        }
    }

    private function loadCorpus(): array
    {
        $raw = file_get_contents(__DIR__.'/../corpus/rules.json');
        $this->assertIsString($raw, 'Corpus file missing');

        return json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
