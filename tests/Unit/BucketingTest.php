<?php

namespace Tests\Unit;

use App\Services\Bucketing;
use PHPUnit\Framework\TestCase;

class BucketingTest extends TestCase
{
    public function test_bucket_returns_value_in_unit_interval(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $b = Bucketing::bucket('some-flag', "user-{$i}", 'seed');
            $this->assertGreaterThanOrEqual(0.0, $b);
            $this->assertLessThanOrEqual(1.0, $b);
        }
    }

    public function test_bucket_is_deterministic(): void
    {
        $a = Bucketing::bucket('new-checkout', 'alice', 'seed-x');
        $b = Bucketing::bucket('new-checkout', 'alice', 'seed-x');
        $this->assertSame($a, $b);
    }

    public function test_bucket_changes_with_seed(): void
    {
        $a = Bucketing::bucket('flag', 'alice', 'seed-a');
        $b = Bucketing::bucket('flag', 'alice', 'seed-b');
        $this->assertNotSame($a, $b);
    }

    public function test_rollout_distribution_within_tolerance(): void
    {
        // 25% rollout across 2000 synthetic users — distribution should fall
        // within ±2.5% of 25%. Generous bound so a CI cold run doesn't flake.
        $hits = 0;
        $total = 2000;
        for ($i = 0; $i < $total; $i++) {
            if (Bucketing::isInRollout('feature-x', "user-{$i}", 'rollout-seed', 25.0)) {
                $hits++;
            }
        }
        $ratio = $hits / $total;
        $this->assertGreaterThan(0.225, $ratio, "Rollout ratio too low: {$ratio}");
        $this->assertLessThan(0.275, $ratio, "Rollout ratio too high: {$ratio}");
    }

    public function test_zero_and_full_rollout_short_circuit(): void
    {
        $this->assertFalse(Bucketing::isInRollout('f', 'u', 's', 0));
        $this->assertTrue(Bucketing::isInRollout('f', 'u', 's', 100));
        $this->assertTrue(Bucketing::isInRollout('f', 'u', 's', 100.5));
        $this->assertFalse(Bucketing::isInRollout('f', 'u', 's', -1));
    }
}
