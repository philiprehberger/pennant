# Pennant SSE broadcaster

A small Node service that fans out flag-change events from Redis pub/sub to
connected SSE sessions. Apache reverse-proxies `/v1/stream` on the API host
to this process on `127.0.0.1:8081`.

```
SDK ──EventSource──> Apache (api.pennant.philiprehberger.com)
                      │
                      └── ProxyPass /v1/stream → 127.0.0.1:8081/stream
                                                    │
                                                    └── Node broadcaster
                                                          ├── validates key vs Laravel API
                                                          ├── subscribes to Redis channel
                                                          ├── replays from ring buffer
                                                          └── forwards events as SSE
```

## Run locally

```bash
# Terminal 1: Laravel API
cd ~/projects/pennant && php artisan serve

# Terminal 2: Redis (required for pub/sub)
redis-server

# Terminal 3: broadcaster
cd ~/projects/pennant/infra/sse-broadcaster
npm install
PENNANT_API_BASE=http://127.0.0.1:8000 npm start
```

## Environment

| Var                    | Default                  |
| ---------------------- | ------------------------ |
| `PENNANT_SSE_PORT`     | `8081`                   |
| `REDIS_URL`            | `redis://127.0.0.1:6379` |
| `PENNANT_API_BASE`     | `http://127.0.0.1:8000`  |
| `PENNANT_PUBSUB_PREFIX`| `flag-updates`           |

## Production

Managed by supervisord — see `infra/supervisor/pennant-sse.conf`.

```
sudo cp infra/supervisor/pennant-sse.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start pennant-sse
sudo supervisorctl status pennant-sse
```

## Endpoints

- `GET /health` — liveness probe, returns `200 {"status":"healthy"}`.
- `GET /stream?environment=<key>&key=<api-key>` — SSE; sends `id:`, `event:`, `data:` frames per spec. Supports `Last-Event-ID` for replay.
