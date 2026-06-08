// Pennant SSE broadcaster.
//
// Responsibilities:
//   - Accept GET /stream?environment=<key>&key=<api-key> from EventSource clients.
//   - Validate the key by calling the Laravel API.
//   - Subscribe to Redis pub/sub channel for (workspace_id, environment_key).
//   - Replay events newer than Last-Event-ID from the ring buffer.
//   - Forward each Redis message as an SSE `data:` frame.
//
// Apache reverse-proxies /v1/stream → http://127.0.0.1:8081/stream. The
// reverse-proxy is configured with ProxyTimeout 86400 so the long-lived
// connection survives.

import http from 'node:http';
import Redis from 'ioredis';

const PORT = Number(process.env.PENNANT_SSE_PORT || 8081);
const REDIS_URL = process.env.REDIS_URL || 'redis://127.0.0.1:6379';
const API_BASE = process.env.PENNANT_API_BASE || 'http://127.0.0.1:8000';
const PUBSUB_PREFIX = process.env.PENNANT_PUBSUB_PREFIX || 'flag-updates';
const HEARTBEAT_MS = 30_000;

/** Validate the API key against Laravel; return { workspaceId } or null. */
async function validateKey(token) {
  if (!token) return null;
  try {
    const res = await fetch(`${API_BASE}/v1/workspaces/current`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
    if (!res.ok) return null;
    const body = await res.json();
    return body?.id ? { workspaceId: body.id } : null;
  } catch (err) {
    console.error('[sse] auth check failed', err);
    return null;
  }
}

/** Replay events newer than lastEventId from the ring buffer. */
async function replay(redis, bufferKey, lastEventId, sendEvent) {
  if (!lastEventId) return 0;
  // Buffer is LPUSH'd so index 0 is newest. Read all 100, filter to those
  // after lastEventId, reverse to oldest-first, emit.
  const raw = await redis.lrange(bufferKey, 0, 99);
  const replayable = [];
  for (const item of raw) {
    try {
      const ev = JSON.parse(item);
      if (ev.id && ev.id > lastEventId) {
        replayable.push(ev);
      }
    } catch {
      // skip malformed
    }
  }
  replayable.reverse();
  for (const ev of replayable) sendEvent(ev);
  return replayable.length;
}

function parseQuery(req) {
  const url = new URL(req.url, `http://localhost:${PORT}`);
  return {
    pathname: url.pathname,
    environment: url.searchParams.get('environment'),
    key: url.searchParams.get('key'),
  };
}

const server = http.createServer(async (req, res) => {
  const { pathname, environment, key } = parseQuery(req);

  if (pathname === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'healthy' }));
    return;
  }

  if (pathname !== '/stream') {
    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'not found' }));
    return;
  }

  if (!environment) {
    res.writeHead(400, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'environment query parameter is required' }));
    return;
  }

  const token = key || (req.headers.authorization?.startsWith('Bearer ') ? req.headers.authorization.slice(7) : null);
  const auth = await validateKey(token);
  if (!auth) {
    res.writeHead(401, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'unauthenticated' }));
    return;
  }

  res.writeHead(200, {
    'Content-Type': 'text/event-stream',
    'Cache-Control': 'no-cache, no-transform',
    Connection: 'keep-alive',
    'X-Accel-Buffering': 'no',
  });
  res.write(': pennant connected\n\n');

  const channel = `${PUBSUB_PREFIX}:${auth.workspaceId}:${environment}`;
  const bufferKey = `flag-events:${auth.workspaceId}:${environment}`;

  const subscriber = new Redis(REDIS_URL, { lazyConnect: true });
  await subscriber.connect();
  await subscriber.subscribe(channel);

  const lastEventId = req.headers['last-event-id'] || null;
  const main = new Redis(REDIS_URL, { lazyConnect: true });
  await main.connect();

  const sendEvent = (ev) => {
    if (!res.writableEnded) {
      res.write(`id: ${ev.id}\n`);
      res.write(`event: ${ev.type || 'message'}\n`);
      res.write(`data: ${JSON.stringify(ev)}\n\n`);
    }
  };

  await replay(main, bufferKey, lastEventId, sendEvent);

  subscriber.on('message', (_chan, raw) => {
    try {
      sendEvent(JSON.parse(raw));
    } catch (err) {
      console.error('[sse] bad event payload', err);
    }
  });

  // Keep the connection alive through any intermediate proxy idle timeouts.
  const heartbeat = setInterval(() => {
    if (!res.writableEnded) res.write(': heartbeat\n\n');
  }, HEARTBEAT_MS);

  const cleanup = async () => {
    clearInterval(heartbeat);
    try { await subscriber.unsubscribe(channel); } catch {}
    try { await subscriber.quit(); } catch {}
    try { await main.quit(); } catch {}
  };

  req.on('close', cleanup);
  res.on('close', cleanup);
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`[sse] broadcaster listening on 127.0.0.1:${PORT}`);
  console.log(`[sse]   redis: ${REDIS_URL}`);
  console.log(`[sse]   api:   ${API_BASE}`);
});

process.on('SIGTERM', () => {
  console.log('[sse] SIGTERM, shutting down');
  server.close(() => process.exit(0));
});
