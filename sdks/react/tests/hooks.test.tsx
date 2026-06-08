import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, act, screen, cleanup } from '@testing-library/react';
import { Pennant, type Snapshot } from '@philiprehberger/pennant';
import { PennantProvider, useBool, useString, useFlag, usePennantReady } from '../src/index.js';

function makeSnapshot(checkoutOn = true, cta = 'Get started'): Snapshot {
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
          variation: checkoutOn,
          bucketing_attribute: 'userId',
          bucketing_seed: 'seed',
          rules: [],
        },
      },
      {
        key: 'cta-copy',
        type: 'string',
        default_value: 'fallback',
        configuration: {
          state: 'on',
          variation: cta,
          bucketing_attribute: 'userId',
          bucketing_seed: 'seed',
          rules: [],
        },
      },
    ],
    segments: [],
  };
}

function sequentialFetch(snapshots: Snapshot[]): typeof globalThis.fetch {
  let idx = 0;
  return vi.fn(async () => {
    const snap = snapshots[Math.min(idx, snapshots.length - 1)];
    idx++;
    return new Response(JSON.stringify(snap), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  }) as unknown as typeof globalThis.fetch;
}

function makeClient(bootstrap: Snapshot, nextRefreshes: Snapshot[] = []): Pennant {
  return new Pennant({
    apiBase: 'http://test',
    apiKey: 'pn_srv_live_x',
    environment: 'prod',
    bootstrap,
    pollIntervalMs: 0,
    EventSourceImpl: null,
    storageKey: null,
    fetch: sequentialFetch([bootstrap, ...nextRefreshes]),
  });
}

describe('React hooks', () => {
  afterEach(() => cleanup());

  it('useBool returns the flag value', () => {
    const client = makeClient(makeSnapshot(true));
    function Probe() {
      const v = useBool('new-checkout', false);
      return <div data-testid="v">{String(v)}</div>;
    }
    render(<PennantProvider client={client}>{<Probe />}</PennantProvider>);
    expect(screen.getByTestId('v').textContent).toBe('true');
    client.destroy();
  });

  it('useString returns the flag value', () => {
    const client = makeClient(makeSnapshot(true, 'Try free trial'));
    function Probe() {
      const v = useString('cta-copy', 'fallback');
      return <div data-testid="v">{v}</div>;
    }
    render(<PennantProvider client={client}>{<Probe />}</PennantProvider>);
    expect(screen.getByTestId('v').textContent).toBe('Try free trial');
    client.destroy();
  });

  it('falls back when flag is missing', () => {
    const client = makeClient(makeSnapshot(true));
    function Probe() {
      const v = useBool('missing-flag', true);
      return <div data-testid="v">{String(v)}</div>;
    }
    render(<PennantProvider client={client}>{<Probe />}</PennantProvider>);
    expect(screen.getByTestId('v').textContent).toBe('true');
    client.destroy();
  });

  it('re-renders with new value after refresh', async () => {
    const before = makeSnapshot(true, 'Get started');
    const after = makeSnapshot(true, 'Try free trial');
    const client = makeClient(before, [after]);

    function Probe() {
      const v = useString('cta-copy', 'fallback');
      return <div data-testid="v">{v}</div>;
    }
    render(<PennantProvider client={client}>{<Probe />}</PennantProvider>);
    expect(screen.getByTestId('v').textContent).toBe('Get started');

    await act(async () => {
      await client.refresh();
    });

    expect(screen.getByTestId('v').textContent).toBe('Try free trial');
    client.destroy();
  });

  it('useFlag returns stable reference when value unchanged', async () => {
    const initial = makeSnapshot(true, 'Hello');
    const same = makeSnapshot(true, 'Hello'); // semantically identical
    const client = makeClient(initial, [same]);

    const refs: unknown[] = [];
    function Probe() {
      const r = useFlag('cta-copy');
      refs.push(r);
      return <div data-testid="reason">{r.reason}</div>;
    }
    render(<PennantProvider client={client}>{<Probe />}</PennantProvider>);
    const firstRef = refs[refs.length - 1];

    await act(async () => {
      await client.refresh();
    });

    expect(screen.getByTestId('reason').textContent).toBe('fallthrough');
    // The hook's cacheRef should have returned the same object reference
    // because value + reason + ruleIndex didn't change.
    expect(refs[refs.length - 1]).toBe(firstRef);
    client.destroy();
  });

  it('usePennantReady reports true once ready resolves', async () => {
    const client = makeClient(makeSnapshot(true));
    function Probe() {
      const ready = usePennantReady();
      return <div data-testid="ready">{ready ? 'yes' : 'no'}</div>;
    }
    await act(async () => {
      render(<PennantProvider client={client}>{<Probe />}</PennantProvider>);
    });
    await act(async () => {
      await Promise.resolve();
    });
    expect(screen.getByTestId('ready').textContent).toBe('yes');
    client.destroy();
  });
});
