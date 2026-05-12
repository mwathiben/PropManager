# Dependency Upgrade Policy

Phase-14 SUPPLY-5: critical-path packages should not silently jump minor versions. Composer's `^X.Y` constraint permits any 12.X.Y → 12.99.Y bump (Laravel does monthly minor releases that have introduced behaviour changes in the past). This document defines the review policy that maps onto the Dependabot PRs introduced in Phase-14 SUPPLY-1.

## Critical-path packages

These packages directly process tenant data, payment data, or are load-bearing in the security stack. Any minor or major version bump requires:

1. **Read the upstream changelog** for the version range being introduced.
2. **Run the full test suite locally** (not just CI) — `php artisan test --parallel`.
3. **Acknowledge in the PR description** that the regression-test surface has been considered.
4. **Wait for green CI** including the composer-audit + npm-audit jobs.

The set:

| Package | Current | Risk surface |
|---------|---------|--------------|
| `laravel/framework` | ^12.0 | Core framework — auth, validation, routing |
| `laravel/sanctum` | ^4.0 | Token auth — every API request |
| `laravel/reverb` | ^1.7 | WebSocket — broadcast leakage risk |
| `sentry/sentry-laravel` | ^4.25 | Error reporting — beforeSend hooks |
| `spatie/laravel-backup` | ^10.2 | Backup integrity — DR runbook depends on it |
| `inertiajs/inertia-laravel` | ^2.0 | View-data leak surface |
| `pragmarx/google2fa-laravel` | ^2.3 | 2FA gate |
| `bacon/bacon-qr-code` | ^3.0 | 2FA enrolment QR |

For the JS side:

| Package | Current | Risk surface |
|---------|---------|--------------|
| `vue` | ^3.4 | Core UI — XSS surface |
| `vite` | ^7.0 | Build chain — supply-chain risk |
| `axios` | ^1.11 | HTTP client — credentials sent on every request |
| `tailwindcss` | ^4.1 | Build chain |
| `pinia` | ^3.0 | State store |

## Patch-only packages

Everything else can be auto-merged on green CI when Dependabot creates a security PR (group `composer-security` / `npm-security`). The Phase-14 SUPPLY-2/3 audit gates verify no new CVE has been introduced.

## Major version bump procedure

For any major (X+1.0.0) bump on a critical-path package:

1. Branch off `main` and pin to the current major while developing.
2. Read the upstream upgrade guide end-to-end.
3. Run the regression test suite + the Browser test suite (`php artisan dusk` — slow, but the Mailpit + email-flow tests catch many regressions Laravel introduces).
4. Squash-merge with a commit message documenting the upgrade.
5. Watch Sentry release for 48h post-deploy (Phase-14 OBSERV-2 release tagging makes this possible — errors are now attributable to the deploy commit).

## Optional: tighten constraints to `~X.Y` (deferred to operator)

The PRD called for tightening constraints from `^X.Y` to `~X.Y` on the critical-path packages. Operator may opt in by editing composer.json:

```json
"laravel/framework": "~12.46",   // was "^12.0"
"laravel/sanctum":   "~4.2",     // was "^4.0"
"laravel/reverb":    "~1.7",     // was "^1.7" (already-tight)
```

The trade-off: minor bumps require an explicit constraint-edit PR. Dependabot will produce one per ecosystem per week (Phase-14 SUPPLY-1 schedule). The operator decides whether the extra friction is worth the extra explicitness; the current `^X.Y` form trusts the maintainer's semver discipline.
