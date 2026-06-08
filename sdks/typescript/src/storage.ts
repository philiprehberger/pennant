import type { Snapshot } from './types.js';

/**
 * Persists the last-good snapshot so the SDK can boot from cache when the
 * network is gone. Three implementations:
 *
 *   - `LocalStorageBackend` — browser; uses window.localStorage
 *   - `MemoryBackend` — fallback when localStorage is unavailable (incognito
 *     quota exceeded, SSR, Node without storageKey set)
 *   - `FileBackend` — Node servers; writes JSON to disk
 *
 * `pickBackend()` returns the best available backend without throwing on
 * the unhappy paths.
 */
export interface StorageBackend {
  read(): Snapshot | null;
  write(snapshot: Snapshot): void;
  clear(): void;
}

export class MemoryBackend implements StorageBackend {
  private snapshot: Snapshot | null = null;
  read(): Snapshot | null {
    return this.snapshot;
  }
  write(snapshot: Snapshot): void {
    this.snapshot = snapshot;
  }
  clear(): void {
    this.snapshot = null;
  }
}

export class LocalStorageBackend implements StorageBackend {
  constructor(private readonly key: string) {}
  read(): Snapshot | null {
    try {
      const raw = globalThis.localStorage?.getItem(this.key);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return isSnapshot(parsed) ? parsed : null;
    } catch {
      // Malformed JSON / quota errors / storage disabled. Treat as no cache.
      return null;
    }
  }
  write(snapshot: Snapshot): void {
    try {
      globalThis.localStorage?.setItem(this.key, JSON.stringify(snapshot));
    } catch {
      // Quota exceeded or disabled — give up silently.
    }
  }
  clear(): void {
    try {
      globalThis.localStorage?.removeItem(this.key);
    } catch {
      // ignore
    }
  }
}

function isSnapshot(v: unknown): v is Snapshot {
  if (!v || typeof v !== 'object') return false;
  const s = v as Snapshot;
  return typeof s.environment === 'string' && typeof s.version === 'string' && Array.isArray(s.flags);
}

export function pickBackend(storageKey: string | null | undefined): StorageBackend {
  if (storageKey === null) return new MemoryBackend();
  const key = storageKey ?? 'pennant';
  if (typeof globalThis !== 'undefined' && typeof globalThis.localStorage !== 'undefined') {
    return new LocalStorageBackend(key);
  }
  return new MemoryBackend();
}
