import { describe, expect, it, vi, beforeEach } from 'vitest';
import { Pennant } from '../src/client.js';
import type { Snapshot } from '../src/types.js';

function snap(overrides: Partial<Snapshot> = {}): Snapshot {
  return {
    environment: 'prod',
    version: '01HF0',
    kind: 'server',
    flags: [
      {
        key: 'new-checkout',
        type: 'bool',
        default_value: false,
        configuration: {
          state: 'on',
          variation: true,
          bucketing_attribute: 'userId',
          bucketing_seed: 'seed',
          rules: [],
        },
      },
      {
        key: 'cta-copy',
        type: 'string',
        default_value: 'Get started',
        configuration: {
          state: 'on',
          variation: 'Try free trial',
          bucketing_attribute: 'userId',
          bucketing_seed: 'seed',
          rules: [
            {
              priority: 0,
              condition: { attribute: 'plan', op: 'equals', value: 'enterprise' },
              variation: 'Talk to sales',
            },
          ],
        },
      },
    ],
    segments: [],
    ...overrides,
  };
}

function fakeFetch(snapshot: Snapshot, opts: { status?: number } = {}): typeof globalThis.fetch {
  return vi.fn(async () => {
    return new Response(JSON.stringify(snapshot), {
      status: opts.status ?? 200,
      headers: { 'Content-Type': 'application/json' },
    });
  }) as unknown as typeof globalThis.fetch;
}

describe('Pennant client', () => {
  beforeEach(() => {
    // Reset globalThis.localStorage between tests.
    (globalThis as any).localStorage = undefined;
  });

  it('boots from bootstrap snapshot synchronously', async () => {
    const flags = new Pennant({
      apiBase: 'http://test',
      apiKey: 'pn_srv_live_x',
      environment: 'prod',
      bootstrap: snap(),
      pollIntervalMs: 0,
      EventSourceImpl: null,
      storageKey: null,
      fetch: fakeFetch(snap()),
    });
    await flags.ready();
    expect(flags.bool('new-checkout', false)).toBe(true);
    expect(flags.string('cta-copy', 'fallback')).toBe('Try free trial');
    flags.destroy();
  });

  it('evaluates targeting rule from context', async () => {
    const flags = new Pennant({
      apiBase: 'http://test',
      apiKey: 'pn_srv_live_x',
      environment: 'prod',
      context: { userId: 'alice', plan: 'enterprise' },
      bootstrap: snap(),
      pollIntervalMs: 0,
      EventSourceImpl: null,
      storageKey: null,
      fetch: fakeFetch(snap()),
    });
    await flags.ready();
    expect(flags.string('cta-copy', 'fallback')).toBe('Talk to sales');
    flags.destroy();
  });

  it('returns fallback when not ready and no bootstrap', () => {
    const flags = new Pennant({
      apiBase: 'http://test',
      apiKey: 'pn_srv_live_x',
      environment: 'prod',
      pollIntervalMs: 0,
      EventSourceImpl: null,
      storageKey: null,
      fetch: vi.fn(() => new Promise(() => {})) as unknown as typeof fetch,
    });
    expect(flags.bool('missing', true)).toBe(true);
    expect(flags.string('missing', 'fallback')).toBe('fallback');
    flags.destroy();
  });

  it('falls back when localStorage cache is malformed', async () => {
    const store = new Map<string, string>();
    store.set('pennant', '{ corrupt json');
    (globalThis as any).localStorage = {
      getItem: (k: string) => store.get(k) ?? null,
      setItem: (k: string, v: string) => store.set(k, v),
      removeItem: (k: string) => store.delete(k),
    };

    const flags = new Pennant({
      apiBase: 'http://test',
      apiKey: 'pn_srv_live_x',
      environment: 'prod',
      bootstrap: snap(),
      pollIntervalMs: 0,
      EventSourceImpl: null,
      fetch: fakeFetch(snap()),
    });
    await flags.ready();
    expect(flags.bool('new-checkout', false)).toBe(true);
    flags.destroy();
  });

  it('emits update events when refresh lands a new snapshot', async () => {
    const newer = snap({ version: 'B', flags: [{ ...snap().flags[0]!, configuration: { ...snap().flags[0]!.configuration!, variation: false } }] });
    const handler = vi.fn();
    const flags = new Pennant({
      apiBase: 'http://test',
      apiKey: 'pn_srv_live_x',
      environment: 'prod',
      pollIntervalMs: 0,
      EventSourceImpl: null,
      storageKey: null,
      fetch: fakeFetch(newer),
    });
    flags.on('update', handler);
    await flags.ready();
    expect(handler).toHaveBeenCalled();
    expect(flags.bool('new-checkout', false)).toBe(false);
    flags.destroy();
  });

  it('handles client snapshot pre-evaluated values', async () => {
    const clientSnap: Snapshot = {
      environment: 'prod',
      version: 'A',
      kind: 'client',
      flags: [
        { key: 'new-checkout', type: 'bool', default_value: false, value: true, reason: 'fallthrough' },
      ],
    };
    const flags = new Pennant({
      apiBase: 'http://test',
      apiKey: 'pn_clt_live_x',
      environment: 'prod',
      bootstrap: clientSnap,
      pollIntervalMs: 0,
      EventSourceImpl: null,
      storageKey: null,
      fetch: fakeFetch(clientSnap),
    });
    await flags.ready();
    expect(flags.bool('new-checkout', false)).toBe(true);
    flags.destroy();
  });
});
