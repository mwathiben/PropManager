#!/usr/bin/env bash
# Phase-11 DEPLOY-1/6/7: production deploy script invoked by
# .github/workflows/deploy.yml over SSH. Idempotent — re-running on
# the same SHA is a no-op aside from cache rebuilds.
#
# Args:
#   $1 - target SHA (informational, used only for logging)
#
# Pre-conditions on the host:
#   - cwd is the application root
#   - git working tree is already at the target SHA (workflow handles
#     fetch+checkout)
#   - composer, php, npm on PATH
#   - .env is populated for production

set -euo pipefail

TARGET_SHA="${1:-HEAD}"
TS() { date -u +%Y-%m-%dT%H:%M:%SZ; }
log() { echo "[deploy $(TS)] $*"; }

log "starting deploy to $TARGET_SHA"

# Phase-14 OBSERV-2: stamp SENTRY_RELEASE in .env so Sentry can
# attribute errors to the specific commit. Without this, an error
# from yesterday looks the same as one from a week-old release —
# regression attribution required commit archaeology.
# The line is upsert-style: replace if present, append otherwise.
if grep -q '^SENTRY_RELEASE=' .env 2>/dev/null; then
  sed -i.bak "s|^SENTRY_RELEASE=.*|SENTRY_RELEASE=${TARGET_SHA}|" .env && rm -f .env.bak
else
  echo "SENTRY_RELEASE=${TARGET_SHA}" >> .env
fi
log "stamped SENTRY_RELEASE=${TARGET_SHA}"

# Maintenance mode keeps user-facing 503 short during migrate+cache.
# --retry tells balancers to retry after N seconds; queue workers are
# unaffected (they don't honor maintenance mode).
php artisan down --retry=15 --refresh=15 || true
trap 'php artisan up || true' EXIT

log "composer install (production)"
# Phase-14 SUPPLY-8: --classmap-authoritative refuses to autoload
# classes not in the classmap. Prevents PSR-4 ambiguity attacks
# (drop a class file into a path Laravel scans and have it loaded
# without the operator noticing). PropManager doesn't use runtime
# class loading in production; tests do, but tests run with the
# full dev classmap.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative --no-progress

log "npm ci + build"
npm ci --silent
npm run build

# Phase-11 DEPLOY-5 follow-up: a migrate --pretend gate runs in CI;
# at deploy time we trust the gate and apply with --force.
log "migrate"
php artisan migrate --force

# Phase-11 DEPLOY-6: warm the caches Laravel skips without them. A
# cold container without these caches pays the full reflection cost
# on every request.
log "cache warming"
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

# Phase-11 DEPLOY-7: long-running workers cache class autoload state.
# Without this, post-deploy jobs continue executing PRE-deploy code
# until the supervisor next restarts them (could be days). queue:restart
# sets a flag the worker reads at the next loop iteration so the next
# job dispatches fresh PHP. Horizon honours the same flag.
log "queue restart"
php artisan queue:restart

log "deploy complete"
