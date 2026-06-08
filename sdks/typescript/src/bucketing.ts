import { createHash } from 'node:crypto';

/**
 * Deterministic bucketing for percentage rollouts.
 *
 * IMPORTANT — load-bearing: this function mirrors the PHP server's
 * App\Services\Bucketing class byte-for-byte. Both implementations consume
 * the same drift corpus (tests/corpus/rules.json at repo root). If you
 * change the hashing strategy, update the PHP version and the corpus first.
 *
 *   bucket(flag_key, identifier, seed) =
 *       parseInt(sha256(flag_key + ":" + identifier + ":" + seed)[0..8], 16)
 *       / 0xffffffff
 */
export function bucket(flagKey: string, identifier: string, seed: string): number {
  const hex = sha256Hex(`${flagKey}:${identifier}:${seed}`).slice(0, 8);
  const int = parseInt(hex, 16);
  return int / 0xffffffff;
}

export function isInRollout(
  flagKey: string,
  identifier: string,
  seed: string,
  percentage: number,
): boolean {
  if (percentage <= 0) return false;
  if (percentage >= 100) return true;
  return bucket(flagKey, identifier, seed) < percentage / 100;
}

function sha256Hex(input: string): string {
  // node:crypto in Node 22+. For browsers we'd use SubtleCrypto but the
  // current SDK targets server + Node-shaped fetch environments first.
  return createHash('sha256').update(input, 'utf8').digest('hex');
}
