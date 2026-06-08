<?php

declare(strict_types=1);

namespace Pennant;

/**
 * Deterministic bucketing for percentage rollouts.
 *
 * Mirrors the Pennant server's App\Services\Bucketing byte-for-byte; the
 * cross-implementation drift corpus at tests/corpus.json is the contract.
 */
final class Bucketing
{
    public static function bucket(string $flagKey, string $identifier, string $seed): float
    {
        $hex = substr(hash('sha256', $flagKey.':'.$identifier.':'.$seed), 0, 8);
        return ((int) hexdec($hex)) / 0xffffffff;
    }

    public static function isInRollout(string $flagKey, string $identifier, string $seed, float $percentage): bool
    {
        if ($percentage <= 0) return false;
        if ($percentage >= 100) return true;
        return self::bucket($flagKey, $identifier, $seed) < $percentage / 100;
    }
}
