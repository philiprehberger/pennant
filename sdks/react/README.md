# @philiprehberger/pennant-react

React adapter for [Pennant](https://github.com/philiprehberger/pennant) — `useFlag` hooks + `PennantProvider`, with selective re-render via `useSyncExternalStore`.

```bash
npm install @philiprehberger/pennant @philiprehberger/pennant-react
```

## Quickstart

```tsx
import { PennantProvider, useBool, useString } from '@philiprehberger/pennant-react';

export function App() {
  return (
    <PennantProvider
      options={{
        apiBase: 'https://api.pennant.philiprehberger.com',
        apiKey: process.env.NEXT_PUBLIC_PENNANT_KEY!,
        environment: 'prod',
        context: { userId: 'alice', plan: 'enterprise' },
      }}
    >
      <Checkout />
      <Hero />
    </PennantProvider>
  );
}

function Checkout() {
  // Re-renders only when `new-checkout-flow` changes — not when other flags change.
  const enabled = useBool('new-checkout-flow', false);
  return enabled ? <NewCheckout /> : <LegacyCheckout />;
}

function Hero() {
  const cta = useString('hero-cta-copy', 'Get started');
  return <button>{cta}</button>;
}
```

## Hooks

| Hook | Returns | Notes |
| --- | --- | --- |
| `useBool(key, fallback)` | `boolean` | Falls back on missing flag / non-boolean variations. |
| `useString(key, fallback)` | `string` | Same. |
| `useNumber(key, fallback)` | `number` | Same. |
| `useJson<T>(key, fallback)` | `T` | Returns the JSON variation by reference; stable between updates. |
| `useFlag(key)` | `EvaluationResult` | Full `{ value, reason, ruleIndex }` — useful for analytics. |
| `usePennantReady()` | `boolean` | Becomes `true` after the first snapshot lands. Useful for SSR / Suspense fences. |
| `usePennant()` | `Pennant` | The underlying instance — for `setContext` / `destroy` / lifecycle subscriptions. |

## Selective re-render

Each hook subscribes to the SDK's `update` event via `useSyncExternalStore`. When a snapshot lands, React re-evaluates `getSnapshot` for each subscribed component. **React's automatic referential-equality bailout means only the components whose specific flag's value changed actually commit.** Components reading unchanged flags skip rendering.

This matters at scale: a 50-key snapshot refresh shouldn't trigger a full app re-render.

## Provider props

```tsx
<PennantProvider options={...}>...</PennantProvider>   // owned: provider constructs + destroys
<PennantProvider client={pennant}>...</PennantProvider> // borrowed: caller owns lifecycle
```

Pass either `options` or `client`. With `options`, the provider constructs the Pennant on mount and calls `destroy()` on unmount.

## License

MIT
