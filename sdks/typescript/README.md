# @philiprehberger/pennant

Pennant SDK — feature flag evaluation with real-time updates, deterministic local bucketing, and offline-resilient persistence.

```bash
npm install @philiprehberger/pennant
```

## Quickstart

```ts
import { Pennant } from '@philiprehberger/pennant';

const flags = new Pennant({
  apiBase: 'https://api.pennant.philiprehberger.com',
  apiKey: process.env.PENNANT_KEY!,
  environment: 'prod',
  context: { userId: 'alice', plan: 'enterprise' },
});

// Reads are synchronous and never throw. Falls back to the supplied default
// until the first snapshot is loaded.
if (flags.bool('new-checkout-flow', false)) {
  // ...
}

const heroCopy = flags.string('hero-cta-copy', 'Get started');
const limits = flags.json('plan-limits', { uploads: 10 });

// Optional: wait for the first snapshot before rendering.
await flags.ready();

// React to changes.
flags.on('update', ({ reason }) => console.log('flags changed', reason));
flags.on('error', (err) => console.error(err));
```

## What's in the box

- **Bootstrap + cache** — first call hits `GET /v1/snapshot`. If a `bootstrap` snapshot is supplied (e.g. from server rendering), the network call is skipped. Last successful snapshot is persisted to `localStorage` (browser) or in-memory (Node) so cold starts with no network still boot.
- **Real-time via SSE** — long-lived `GET /v1/stream` connection. Each flag change triggers a snapshot refresh; the SDK applies the new state and emits `update`.
- **Polling fallback** — when SSE is unavailable (no `EventSource` in the environment, or the connection drops), the SDK polls `GET /v1/snapshot` at `pollIntervalMs` (default 30s).
- **Local rule evaluation** — server keys receive raw rules; the SDK evaluates them locally against the current context. Bucketing is deterministic — the same `(flagKey, userId, seed)` triple always lands in the same bucket. The SDK's expression evaluator mirrors the server's PHP implementation; both are kept in sync by `tests/corpus/rules.json` at the repo root.
- **Client-key safety** — keys starting with `pn_clt_` receive *pre-evaluated* values (the server resolves rules against the context attached to the key request). Rule logic never reaches the browser, so client keys can be embedded in front-end bundles.

## Options

| Option | Default | Notes |
| ------ | ------- | ----- |
| `apiBase` | — | Required. e.g. `https://api.pennant.philiprehberger.com`. |
| `apiKey` | — | Required. `pn_srv_…` or `pn_clt_…`. |
| `environment` | — | Required. The environment key (e.g. `prod`). |
| `context` | `{}` | Evaluation context attached to every read. |
| `bootstrap` | — | Pre-fetched snapshot (e.g. SSR). Skips the first network call. |
| `pollIntervalMs` | `30_000` | Polling fallback. Set to `0` to disable. |
| `storageKey` | `'pennant'` | `localStorage` key. `null` disables persistence. |
| `fetch` | `globalThis.fetch` | Override for tests / custom transport. |
| `EventSourceImpl` | `globalThis.EventSource` | Override; `null` disables SSE. |

## Lifecycle events

- `ready` — first snapshot has been applied (bootstrap, cache, or network).
- `update` — snapshot or context changed; downstream reads will return new values.
- `connected` — SSE connection opened.
- `disconnected` — SSE connection dropped; polling fallback takes over.
- `error` — any background error (fetch failure, malformed snapshot, etc.). Reads continue to return previous values.

## License

MIT
