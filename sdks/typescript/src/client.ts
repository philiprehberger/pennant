import { evaluateFlag } from './evaluator.js';
import { pickBackend, type StorageBackend } from './storage.js';
import type {
  EvaluationContext,
  EvaluationResult,
  FlagDefinition,
  FlagValue,
  LifecycleEvent,
  LifecycleHandler,
  PennantOptions,
  SegmentDefinition,
  Snapshot,
} from './types.js';

/**
 * The Pennant SDK.
 *
 * ```ts
 * const flags = new Pennant({
 *   apiBase: 'https://api.pennant.philiprehberger.com',
 *   apiKey: process.env.PENNANT_KEY!,
 *   environment: 'prod',
 *   context: { userId: 'alice', plan: 'enterprise' },
 * });
 *
 * await flags.ready();
 *
 * if (flags.bool('new-checkout', false)) { ... }
 * ```
 *
 * Reads are synchronous and never throw. If the SDK isn't ready yet, reads
 * fall back to the supplied default. Bootstrap snapshot is fetched in the
 * background; SSE keeps the in-memory state in sync; persistent cache
 * absorbs offline starts.
 */
export class Pennant {
  private readonly options: Required<PennantOptions> & { context: EvaluationContext };
  private readonly storage: StorageBackend;
  private readonly handlers = new Map<LifecycleEvent, Set<LifecycleHandler<any>>>();
  private snapshot: Snapshot | null = null;
  private segments = new Map<string, SegmentDefinition>();
  private isReady = false;
  private readyResolvers: Array<() => void> = [];
  private pollTimer: ReturnType<typeof setInterval> | null = null;
  private sseInstance: EventSource | null = null;
  private destroyed = false;

  constructor(options: PennantOptions) {
    const opts = options;
    this.options = {
      pollIntervalMs: 30_000,
      storageKey: 'pennant',
      context: {},
      bootstrap: undefined as unknown as Snapshot,
      fetch: globalThis.fetch?.bind(globalThis)!,
      EventSourceImpl:
        typeof globalThis.EventSource !== 'undefined' ? globalThis.EventSource : null,
      ...opts,
    } as Required<PennantOptions> & { context: EvaluationContext };

    this.storage = pickBackend(this.options.storageKey);

    if (opts.bootstrap) {
      this.applySnapshot(opts.bootstrap);
      this.markReady();
    } else {
      // Cached snapshot for instant-ready on cold start. Background refresh
      // still runs; SSE / polling will overwrite once the network responds.
      const cached = this.storage.read();
      if (cached) {
        this.applySnapshot(cached);
        this.markReady();
      }
    }

    if (!this.destroyed) {
      void this.refreshInternal().catch((err) => this.emit('error', err));
      this.startSse();
      this.startPolling();
    }
  }

  // ----- Lifecycle -----

  /** Resolves when the first snapshot has been applied (from network, bootstrap, or cache). */
  ready(): Promise<void> {
    if (this.isReady) return Promise.resolve();
    return new Promise((resolve) => {
      this.readyResolvers.push(resolve);
    });
  }

  on<T = unknown>(event: LifecycleEvent, handler: LifecycleHandler<T>): () => void {
    let set = this.handlers.get(event);
    if (!set) {
      set = new Set();
      this.handlers.set(event, set);
    }
    set.add(handler as LifecycleHandler);
    return () => set!.delete(handler as LifecycleHandler);
  }

  destroy(): void {
    this.destroyed = true;
    if (this.pollTimer) clearInterval(this.pollTimer);
    if (this.sseInstance) this.sseInstance.close();
    this.handlers.clear();
  }

  // ----- Reads -----

  bool(key: string, fallback: boolean): boolean {
    const v = this.evaluate(key).value;
    return typeof v === 'boolean' ? v : fallback;
  }

  string(key: string, fallback: string): string {
    const v = this.evaluate(key).value;
    return typeof v === 'string' ? v : fallback;
  }

  number(key: string, fallback: number): number {
    const v = this.evaluate(key).value;
    return typeof v === 'number' ? v : fallback;
  }

  json<T = unknown>(key: string, fallback: T): T {
    const v = this.evaluate(key).value;
    if (v && typeof v === 'object') return v as T;
    return fallback;
  }

  evaluate(key: string, overrideContext?: EvaluationContext): EvaluationResult {
    if (!this.snapshot) {
      return { value: null, reason: 'default', ruleIndex: null };
    }
    const flag = this.snapshot.flags.find((f) => f.key === key);
    if (!flag) {
      return { value: null, reason: 'default', ruleIndex: null };
    }
    const ctx = overrideContext ?? this.options.context;

    if (this.snapshot.kind === 'client') {
      // Server already pre-evaluated for the context attached to the key
      // bootstrap; client snapshots don't include rule logic at all.
      return { value: flag.value ?? null, reason: flag.reason ?? 'default', ruleIndex: null };
    }

    return evaluateFlag(flag, ctx, (segmentKey) => this.segments.get(segmentKey) ?? null);
  }

  /** Update the evaluation context. Causes downstream reads to re-evaluate. */
  setContext(context: EvaluationContext): void {
    (this.options as { context: EvaluationContext }).context = context;
    this.emit('update', { reason: 'context_changed' });
  }

  /** Force a fresh snapshot fetch. Useful for tests + opt-in manual refresh. */
  async refresh(): Promise<void> {
    return this.refreshInternal();
  }

  // ----- Internal -----

  private async refreshInternal(): Promise<void> {
    const url = new URL(`${this.options.apiBase}/v1/snapshot`);
    url.searchParams.set('environment', this.options.environment);
    if (this.isClientKey()) {
      const contextStr = JSON.stringify(this.options.context);
      const b64 = base64url(contextStr);
      url.searchParams.set('context', b64);
    }
    const res = await this.options.fetch(url.toString(), {
      headers: { Authorization: `Bearer ${this.options.apiKey}`, Accept: 'application/json' },
    });
    if (!res.ok) {
      throw new Error(`pennant: snapshot fetch failed ${res.status}`);
    }
    const snap = (await res.json()) as Snapshot;
    this.applySnapshot(snap);
    this.storage.write(snap);
    this.markReady();
    this.emit('update', { reason: 'snapshot_refresh' });
  }

  private applySnapshot(snap: Snapshot): void {
    this.snapshot = snap;
    this.segments = new Map<string, SegmentDefinition>();
    if (snap.segments) {
      for (const seg of snap.segments) this.segments.set(seg.key, seg);
    }
  }

  private markReady(): void {
    if (this.isReady) return;
    this.isReady = true;
    const resolvers = this.readyResolvers;
    this.readyResolvers = [];
    for (const r of resolvers) r();
    this.emit('ready', undefined);
  }

  private startSse(): void {
    const EventSourceCtor = this.options.EventSourceImpl;
    if (!EventSourceCtor) return;
    const url = `${this.options.apiBase}/v1/stream?environment=${encodeURIComponent(
      this.options.environment,
    )}&key=${encodeURIComponent(this.options.apiKey)}`;
    try {
      const sse = new EventSourceCtor(url);
      sse.onopen = () => this.emit('connected', undefined);
      sse.onerror = (err) => this.emit('disconnected', err);
      sse.onmessage = () => {
        // The broadcaster signals a change; refresh the snapshot to pick up
        // the new state. This trades one round-trip for simpler event
        // protocol on the wire.
        void this.refreshInternal().catch((err) => this.emit('error', err));
      };
      this.sseInstance = sse;
    } catch (err) {
      this.emit('error', err);
    }
  }

  private startPolling(): void {
    if (this.options.pollIntervalMs <= 0) return;
    this.pollTimer = setInterval(() => {
      if (this.destroyed) return;
      void this.refreshInternal().catch((err) => this.emit('error', err));
    }, this.options.pollIntervalMs);
  }

  private isClientKey(): boolean {
    return this.options.apiKey.startsWith('pn_clt_');
  }

  private emit<T>(event: LifecycleEvent, payload: T): void {
    const set = this.handlers.get(event);
    if (!set) return;
    for (const h of set) {
      try {
        (h as LifecycleHandler<T>)(payload);
      } catch (err) {
        // Don't let one bad handler kill the SDK.
        // eslint-disable-next-line no-console
        console.error('pennant: handler threw', err);
      }
    }
  }
}

function base64url(input: string): string {
  // Node 22 supports Buffer.from(...).toString('base64'); browsers have btoa.
  const b64 =
    typeof Buffer !== 'undefined'
      ? Buffer.from(input, 'utf-8').toString('base64')
      : globalThis.btoa(input);
  return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
