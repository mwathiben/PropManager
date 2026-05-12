# PropManager

Multi-tenant property-management SaaS for the Kenyan market: rent
collection, M-Pesa / Paystack / IntaSend payments, water billing, KYC,
and deposit management.

## Stack

- **Backend**: Laravel 12 on PHP 8.4, MySQL 8.0
- **Frontend**: Inertia.js + Vue 3 + Tailwind v4 + shadcn-vue
- **Real-time**: Laravel Reverb (WebSockets)
- **Tests**: PHPUnit 11.5 via ParaTest, Dusk for E2E
- **Code style**: Pint (enforced in CI)

## Local development

```bash
# Bootstrap
composer install
npm install
cp .env.example .env
php artisan key:generate

# Local dev uses MySQL or SQLite (default in .env.example).
php artisan migrate
php artisan db:seed   # optional fixtures

# Dev server
php artisan serve
npm run dev
```

Local devs flip `APP_DEBUG=true` and `LOG_LEVEL=debug` in their `.env`
explicitly — both default to `false` / `warning` in the committed
example so a copy-and-deploy never leaks stack traces or PII.

## Testing

```bash
# Lint
vendor/bin/pint --test

# Full parallel suite (faster than --filter for full regressions)
php artisan test --parallel

# Focused
php artisan test --filter=PaymentVerification
```

Email-flow tests use Mailpit (`http://localhost:8025`). The trait at
`tests/Traits/InteractsWithMailpit.php` is parallel-safe — assertions
scope by recipient via `assertEmailCountFor()` and friends.

## Deployment

### Pre-launch checklist

1. **Create the production environment in GitHub**
   (Settings → Environments → New: `production`).
2. **Add required environment secrets**:
   - `DEPLOY_SSH_KEY` — private key with access to the production host
   - `DEPLOY_HOST` — `user@host`
   - `DEPLOY_PATH` — absolute path to the application root on the host
3. **Optional environment variables** (`vars`, not `secrets`):
   - `PRODUCTION_URL` — surfaces in the GitHub Deployments tab
4. **Bootstrap the production `.env`** by copying `.env.production.example`
   on the host, then populating every blank secret. The production-config
   validator (`AppServiceProvider::validateProductionSecurity`) will
   refuse to boot on critical misconfig:
   - `APP_KEY` empty
   - `APP_DEBUG=true` with `APP_ENV != local`
   - `SESSION_ENCRYPT=false`
   - `SESSION_SECURE_COOKIE=false`
   - `MAIL_MAILER` in `{log, array}`

   And will log error-level warnings (visible in Sentry once
   `SENTRY_LARAVEL_DSN` is set) for:
   - HSTS disabled
   - Reverb credentials still on the historical placeholder
   - `SENTRY_LARAVEL_DSN` empty
   - `LOG_LEVEL` at debug or info
   - `KENYA_DPA_REGISTRATION` empty while `KENYA_DPA_ENABLED=true`
   - `BCRYPT_ROUNDS` below 12

### Deploys

Deploys are automated via `.github/workflows/deploy.yml`. It fires
when the CI workflow completes successfully on `main` and is a graceful
no-op until the `production` environment has its secrets configured.

```text
push to main
  → CI workflow (lint + tests + frontend build)
  → Deploy workflow (workflow_run trigger, gated on CI success)
    → checkout target SHA on runner
    → create GitHub Deployment (visible in Deployments tab)
    → ssh into DEPLOY_HOST, run scripts/deploy.sh
    → mark Deployment status success / failure
```

`scripts/deploy.sh` is idempotent:

```text
1. php artisan down --retry=15
2. composer install --no-dev --optimize-autoloader
3. npm ci && npm run build
4. php artisan migrate --force
5. php artisan {config,route,event,view}:cache  (cold-start warmup)
6. php artisan queue:restart  (workers re-read class autoload)
7. php artisan up
```

A manual deploy of a specific SHA:

```text
Actions → Deploy → Run workflow → input the target SHA → Run
```

### Rollback

```bash
# Find the previous green SHA
gh deployment list --repo mwathiben/PropManager --limit 10

# Trigger a re-deploy of the prior SHA
gh workflow run deploy --field sha=<previous-sha>
```

### Health checks

The load balancer must probe `/api/v1/health` (NOT `/up` — removed in
DEPLOY-8). The endpoint returns 200 only when DB, Redis, the default
queue, and the webhook dead-letter depth are all green; 503 on
degradation.

## Key rotation

See `docs/runbooks/key-rotation.md` for:

- APP_KEY rotation (`php artisan crypt:rotate`)
- Per-landlord bank webhook secret rotation
  (`php artisan webhook:rotate-secret`)

Both procedures write `SecurityLog` rows for forensic traceability.

## Audit cycle

Defensive security audits live as JSON PRDs in repo root:

- `phase-3-audit-prd.json` — SCOPE / PERF (46 findings, closed)
- `phase-4-audit-prd.json` — CONC / VALID / AUDIT / HANDLE (55, closed)
- `phase-5-audit-prd.json` — PRIV / RATE / CRYPTO / OBS (54 + 4-finding cleanup, closed)
- `phase-11-audit-prd.json` — DEPLOY / SECRETS (21, in progress)

Each PRD's `closeout` section maps every finding to the commit that
closed it.

## Contributing

- Code style: `vendor/bin/pint` (CI gate)
- Tests must pass `--parallel` before merging
- Migrations must be backward-compatible when shipped to populated
  databases (the CI pretend-migrate gate catches syntax errors; full
  production-shape rehearsal is the operator's responsibility)
- Don't commit secrets — `.env.example` is the local template,
  `.env.production.example` is the production template

## License

MIT. See `LICENSE` for the framework dependency licenses.
