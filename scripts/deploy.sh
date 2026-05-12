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

# Maintenance mode keeps user-facing 503 short during migrate+cache.
# --retry tells balancers to retry after N seconds; queue workers are
# unaffected (they don't honor maintenance mode).
php artisan down --retry=15 --refresh=15 || true
trap 'php artisan up || true' EXIT

log "composer install (production)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

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
