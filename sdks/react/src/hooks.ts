import { useCallback, useRef, useSyncExternalStore } from 'react';
import type { EvaluationContext, EvaluationResult } from '@philiprehberger/pennant';
import { usePennant } from './provider.js';

/**
 * Read a boolean flag. Returns `fallback` until the first snapshot lands or
 * if the flag isn't a boolean. Re-renders only when this specific flag's
 * value changes.
 */
export function useBool(key: string, fallback: boolean, context?: EvaluationContext): boolean {
  const pennant = usePennant();
  const subscribe = useCallback(
    (onChange: () => void) => pennant.on('update', onChange),
    [pennant],
  );
  const get = useCallback(() => {
    const v = pennant.evaluate(key, context).value;
    return typeof v === 'boolean' ? v : fallback;
  }, [pennant, key, fallback, context]);
  return useSyncExternalStore(subscribe, get, get);
}

export function useString(key: string, fallback: string, context?: EvaluationContext): string {
  const pennant = usePennant();
  const subscribe = useCallback(
    (onChange: () => void) => pennant.on('update', onChange),
    [pennant],
  );
  const get = useCallback(() => {
    const v = pennant.evaluate(key, context).value;
    return typeof v === 'string' ? v : fallback;
  }, [pennant, key, fallback, context]);
  return useSyncExternalStore(subscribe, get, get);
}

export function useNumber(key: string, fallback: number, context?: EvaluationContext): number {
  const pennant = usePennant();
  const subscribe = useCallback(
    (onChange: () => void) => pennant.on('update', onChange),
    [pennant],
  );
  const get = useCallback(() => {
    const v = pennant.evaluate(key, context).value;
    return typeof v === 'number' ? v : fallback;
  }, [pennant, key, fallback, context]);
  return useSyncExternalStore(subscribe, get, get);
}

export function useJson<T>(key: string, fallback: T, context?: EvaluationContext): T {
  const pennant = usePennant();
  const subscribe = useCallback(
    (onChange: () => void) => pennant.on('update', onChange),
    [pennant],
  );
  const get = useCallback(() => {
    const v = pennant.evaluate(key, context).value;
    return v && typeof v === 'object' ? (v as T) : fallback;
  }, [pennant, key, fallback, context]);
  return useSyncExternalStore(subscribe, get, get);
}

/**
 * Read the raw evaluation result (value + reason + matched rule).
 *
 * Useful for analytics / debugging — surfaces which rule fired and why.
 *
 * Memoizes the returned object so consecutive reads with the same outcome
 * return the same reference — necessary for useSyncExternalStore to bail out.
 */
export function useFlag(key: string, context?: EvaluationContext): EvaluationResult {
  const pennant = usePennant();
  const cacheRef = useRef<EvaluationResult | null>(null);
  const subscribe = useCallback(
    (onChange: () => void) => pennant.on('update', onChange),
    [pennant],
  );
  const get = useCallback(() => {
    const fresh = pennant.evaluate(key, context);
    const prev = cacheRef.current;
    if (
      prev &&
      prev.value === fresh.value &&
      prev.reason === fresh.reason &&
      prev.ruleIndex === fresh.ruleIndex
    ) {
      return prev;
    }
    cacheRef.current = fresh;
    return fresh;
  }, [pennant, key, context]);
  return useSyncExternalStore(subscribe, get, get);
}
