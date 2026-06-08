<?php

declare(strict_types=1);

namespace Pennant\Tests;

use Pennant\Bucketing;
use Pennant\RuleEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Round-trips the cross-implementation rule-engine corpus through the
 * Pennant PHP SDK. Same corpus, run by the Laravel server in
 * tests/Feature/RuleEngineCorpusTest.php and by the TS SDK in
 * sdks/typescript/tests/corpus.test.ts. All three implementations must
 * produce identical results for every tuple.
 */
class CorpusTest extends TestCase
{
    public function test_rule_engine_corpus(): void
    {
        $corpus = $this->loadCorpus();
        $segments = $corpus['segments'];
        $resolver = fn (string $key): ?array =>
            isset($segments[$key]) ? ['condition' => $segments[$key]] : null;
        $evaluator = new RuleEvaluator($resolver);

        foreach ($corpus['cases'] as $case) {
            $actual = $evaluator->evaluate($case['condition'], $case['context']);
            $this->assertSame(
                $case['expected'],
                $actual,
                "Corpus case '{$case['name']}' failed",
            );
        }
    }

    public function test_bucketing_corpus(): void
    {
        $corpus = $this->loadCorpus();
        foreach ($corpus['bucketing']['cases'] as $case) {
            $actual = Bucketing::bucket($case['flagKey'], $case['identifier'], $case['seed']);
            $this->assertEqualsWithDelta(
                $case['expected'],
                $actual,
                1e-10,
                "Bucketing drift for {$case['flagKey']}/{$case['identifier']}/{$case['seed']}",
            );
        }
    }

    private function loadCorpus(): array
    {
        $path = __DIR__.'/../../../tests/corpus/rules.json';
        return json_decode(file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
