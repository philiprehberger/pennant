# Pennant

> A portfolio API: production-shaped feature flag / remote config service with a targeting rule engine, real-time push via Server-Sent Events, multi-environment workflow, audit log, and SDKs with offline-resilient caching.

- **Docs / dashboard:** [pennant.philiprehberger.com](https://pennant.philiprehberger.com) *(coming soon)*
- **API host:** [api.pennant.philiprehberger.com](https://api.pennant.philiprehberger.com) *(coming soon)*
- **Stack:** Laravel 13 + MySQL + Redis + Filament v4 + Node SSE broadcaster + Next.js 16 + Scalar
- **Plan:** see `~/projects/income-ops/.scratch/plans/feature_flag_api_portfolio.md`

This is not a production service. It's a portfolio demonstration that the same architect can ship the *whole* product surface a product team expects — REST endpoints, a real rule engine, real-time SDK push, a Filament admin with audit trails, generated SDKs across browser + server stacks, and a deploy story — not just an API behind a README.

## Repo layout

```
pennant/
├── app/             Laravel 13 application (API + Filament admin)
├── bootstrap/       Laravel
├── config/          Laravel
├── database/        Migrations, seeders, factories
├── public/          Laravel public root (DocumentRoot on the API host)
├── resources/       Blade views, Filament views (later phases)
├── routes/          API + web routes
├── storage/         Logs, framework cache, app files
├── tests/           Pest + PHPUnit
│   └── corpus/      Cross-implementation rule-engine drift corpus
│
├── openapi/
│   └── spec.yaml    OpenAPI 3.1 — source of truth, drives SDKs and tests
│
├── web/             Next.js 16 docs + marketing + live-demo site (Phase 8)
│
├── sdks/
│   ├── typescript/  TS core + React adapter (Phases 5–6)
│   ├── php/         PHP / Laravel SDK (Phase 7)
│   └── python/      Python / Django / FastAPI SDK (Phase 7)
│
├── infra/
│   ├── apache/      Vhost + Let's Encrypt configs (canonical copies)
│   ├── cron/        Scheduled jobs (audit cold archive)
│   ├── supervisor/  supervisord drop-ins (SSE broadcaster)
│   └── sse-broadcaster/  Node fan-out worker
│
└── scripts/
    └── deploy/      Atomic release-based deploy (release dir + symlink)
```

## Local development

### Prerequisites

- PHP 8.3+
- Composer 2.9+
- Node 22+ / npm 10+
- MySQL 8 running locally on `127.0.0.1:3306`
- Redis 7+ running locally on `127.0.0.1:6379` (only required for Phase 4+ SSE / pub-sub; everything before that is fine without it)

### Setup

```bash
composer install
cp .env.example .env    # then edit DB_PASSWORD if your local MySQL needs one
php artisan key:generate

# One-time: create the local MySQL database + user (will prompt for sudo)
sudo mysql -e "CREATE DATABASE pennant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
               CREATE USER 'pennant'@'localhost' IDENTIFIED BY ''; \
               GRANT ALL PRIVILEGES ON pennant.* TO 'pennant'@'localhost'; \
               FLUSH PRIVILEGES;"

php artisan migrate
php artisan serve   # http://localhost:8000

# Admin: create yourself a user, then sign in at http://localhost:8000/admin
php artisan pennant:seed-admin --email=you@example.com
```

### Validating the OpenAPI spec

```bash
npx @stoplight/spectral-cli lint openapi/spec.yaml
```

CI runs this on every push. Controllers conform to the spec, not the other way around.

### Running the rule-engine corpus

```bash
php artisan test --filter=RuleEngineCorpusTest
```

Every implementation (PHP server, TypeScript SDK, future SDKs) runs the same `tests/corpus/rules.json` fixture in its native test runner. Drift between implementations corrupts experiment data — the corpus catches that drift in CI.

## Deployment

Both halves build locally and rsync to the EC2 host. No CI-hosted builds.

```bash
cp .env.deployment.example .env.deployment

# Deploy the API (atomic release with shared .env + storage symlinks)
npm run deploy

# Verify
bash scripts/deploy/health-check.sh https://api.pennant.philiprehberger.com/v1/healthz
```

The deploy script uses the same atomic-release pattern as `webhook-relay`: release-based switches, shared `.env` and `storage/`, automatic cleanup of old releases, rollback via `npm run deploy:rollback`.

Apache vhosts and Let's Encrypt SSL are pre-provisioned on the EC2 host. The canonical vhost files are tracked in `infra/apache/` — if you change them on the server, rsync them back into the repo.

## Roadmap

Phase 1 (scaffold + OpenAPI spec) is in progress. See the plan for the rest:

1. Skeleton + spec
2. Flag CRUD + admin
3. Rule engine + evaluation
4. SSE broadcaster + real-time push
5. TypeScript SDK
6. React adapter
7. Server-side SDKs (PHP, Python)
8. Docs site + Live Demo
9. Deploy + polish
10. Portfolio cross-linking

## License

MIT
