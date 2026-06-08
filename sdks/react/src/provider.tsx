import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { Pennant, type PennantOptions } from '@philiprehberger/pennant';

/**
 * React provider that owns a Pennant instance.
 *
 * One Provider per app — wrap your root tree. Children read flags via
 * `useBool` / `useString` / `useNumber` / `useJson` hooks, which subscribe
 * to flag updates via `useSyncExternalStore` so only the components reading
 * a changed flag re-render.
 *
 * ```tsx
 * <PennantProvider options={{ apiBase, apiKey, environment, context }}>
 *   <App />
 * </PennantProvider>
 * ```
 *
 * The provider accepts EITHER `options` (it constructs + owns the Pennant)
 * or `client` (you own it and pass it in). One of the two is required.
 */
export interface PennantProviderProps {
  options?: PennantOptions;
  client?: Pennant;
  children: ReactNode;
}

const PennantContext = createContext<Pennant | null>(null);

export function PennantProvider({ options, client, children }: PennantProviderProps): ReactElement {
  // If `client` is supplied we use it directly; otherwise we construct from
  // `options`. We avoid recreating on every render by memoizing on the
  // option fields that matter (api base / key / env).
  const owned = useMemo<Pennant | null>(() => {
    if (client) return null;
    if (!options) throw new Error('PennantProvider needs either `options` or `client`.');
    return new Pennant(options);
  }, [client, options?.apiBase, options?.apiKey, options?.environment]);

  const instance = client ?? owned!;

  useEffect(() => {
    return () => {
      if (owned) owned.destroy();
    };
  }, [owned]);

  return <PennantContext.Provider value={instance}>{children}</PennantContext.Provider>;
}

export function usePennant(): Pennant {
  const ctx = useContext(PennantContext);
  if (!ctx) {
    throw new Error('usePennant: no <PennantProvider> in tree.');
  }
  return ctx;
}

/**
 * Subscribe to the SDK's `ready` state. Useful for guarding a render path
 * until the first snapshot has landed.
 *
 * ```tsx
 * const ready = usePennantReady();
 * if (!ready) return <Skeleton />;
 * ```
 */
export function usePennantReady(): boolean {
  const pennant = usePennant();
  const [ready, setReady] = useState(false);
  useEffect(() => {
    let cancelled = false;
    void pennant.ready().then(() => {
      if (!cancelled) setReady(true);
    });
    return () => {
      cancelled = true;
    };
  }, [pennant]);
  return ready;
}
