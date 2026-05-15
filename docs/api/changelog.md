# API changelog

Consumer-facing release notes for the PropManager API. Each entry
documents what changed in the OpenAPI spec between baselines.

For deprecated routes (entries with both `deprecated_at` and a future
`sunset_at`), see [`deprecations.md`](./deprecations.md). For the
operator process behind every entry, see
[`docs/runbooks/api-deprecation.md`](../runbooks/api-deprecation.md).

The structural diff is produced by `php artisan api:changelog`
(API-DOC-3). The prose `reason:` line is the operator's responsibility
— the command surfaces the delta, the human authors the rationale.

<!-- changelog:entries -->

## 2026-05-15

### Added

- v1 baseline: every path currently served at `/api/v1/*` is at its
  baseline shape. Subsequent entries record diffs against this
  snapshot.
