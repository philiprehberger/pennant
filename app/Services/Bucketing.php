<?php

namespace App\Services;

/**
 * Deterministic bucketing for percentage rollouts.
 *
 * IMPORTANT — load-bearing: every SDK ships its own implementation of this
 * function, and the cross-implementation corpus at tests/corpus/rules.json
 * round-trips a fixed set of inputs through every implementation. If the
 * server and a SDK disagree on bucket placement, percentage rollouts will
 * show different users different values across page refreshes — a
 * credibility-destroying bug in a flag system. Change this function only by
 * editing the corpus first.
 *
 *   bucket(flag_key, identifier, seed) =
 *       parseInt(sha256(flag_key + ":" + identifier + ":" + seed)[0..8], 16)
 *       / 0xffffffff
 *
 * Returns a float in [0, 1].
 */
final class Bucketing
{
    public static function bucket(string $flagKey, string $identifier, string $seed): float
    {
        $hash = hash('sha256', $flagKey.':'.$identifier.':'.$seed);
        $hex = substr($hash, 0, 8);
        $int = (int) hexdec($hex);

        return $int / 0xffffffff;
    }

    public static function isInRollout(string $flagKey, string $identifier, string $seed, float $percentage): bool
    {
        if ($percentage <= 0) {
            return false;
        }
        if ($percentage >= 100) {
            return true;
        }

        return self::bucket($flagKey, $identifier, $seed) < $percentage / 100;
    }
}
