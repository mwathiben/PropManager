# Autoscale Readiness (Phase-22 PERF-SCALE)

PropManager has only ever run as a single app instance. This runbook is
the checklist + the known-gaps record for running **N app instances
behind a load balancer**.

## The statelessness requirements

An app that autoscales must be stateless — any instance can serve any
request, and an instance can be destroyed at any time.

| Requirement | Status | Enforcement |
|-------------|--------|-------------|
| `SESSION_DRIVER` externalised (redis/database, not file/array) | enforced | production config validator warns (PERF-SCALE-1) |
| `CACHE_STORE` externalised (redis) | enforced | production config validator warns (PERF-SCALE-1) |
| Queue is database/redis, not sync | already true | Phase-16 |
| Scheduler runs on exactly one instance | required | see "Scheduler" below |
| Uploads on a shared disk, not local | **NOT yet — see gaps** | `FILESYSTEM_DISK` validator warning (BACKUP-5) |
| No in-process cross-request state | audited clean | no static mutable cross-request state found |

The production config validator (`AppServiceProvider::collectProductionWarnings`)
surfaces the session/cache/filesystem gaps at boot — check the warning
log on a production deploy.

## PERF-SCALE-3 — CLOSED Phase 58

**Status**: closed 2026-05-18.

Pre-Phase-58 the codebase had 28 hardcoded local-disk call sites across
`app/`. These hardcoded the local driver instead of the configurable
default disk, so even with `FILESYSTEM_DISK=s3` set, those paths still
wrote to one host. KYC documents, lease documents, water-reading photos,
invoice PDFs, GDPR exports, OCR temp files were all bound to a single
host's disk — the single biggest horizontal-scale blocker.

Phase 58 shipped `App\Services\Storage\TenantDiskResolver` + the
`Storage::tenant()` facade macro + the
`config('filesystems.tenant_disk')` knob. All 28 callsites were
refactored to flow through `Storage::tenant()`. Phase 22's
`LOCAL_DISK_CALLSITE_BASELINE` dropped from 28 to 0; the shrink-only
ratchet now treats any re-introduction as a PR-blocker.

### Operator workflow to flip to S3

1. Provision the S3 bucket + IAM credentials (out-of-band; standard AWS
   setup). Set the bucket policy to require server-side encryption on
   PUT for KYC / lease document key prefixes.
2. Populate `AWS_*` env vars in production `.env` (these already drive
   the existing `s3` disk in `config/filesystems.php`).
3. Set `FILESYSTEM_TENANT_DISK=s3` in production `.env`.
4. Restart the application.

**No DB migration is required**: the path strings stored in
`Document.file_path` / `WaterReading.photo_path` / `Lease.lease_doc_path`
/ `Invoice.pdf_path` stay the same — only the disk used to access them
changes.

### `path()` caveat

`Filesystem::path($relative)` returns an absolute path on the local
driver but throws on s3 (where the concept doesn't apply). Two call
sites use `path()` for subprocess/ZipArchive flows:

- `app/Services/DataExportService.php` at line 90 — ZipArchive needs a
  real filesystem path. Mitigation when flipped to s3: download to a
  temp dir first (TempFileResolver pattern).
- `app/Services/OcrService.php` at line 288 — Tesseract subprocess
  reads from the file path. Same mitigation pattern.

Document the operator workaround when staging the s3 cutover.

See [storage.md](storage.md) for the full storage runbook.

## Scheduler — single-instance only

Only ONE instance may run the cron, or every scheduled task
double-fires. Two safe patterns:

- a dedicated scheduler instance running `php artisan schedule:work`
  (the rest of the fleet does not run the scheduler at all); OR
- `->onOneServer()` on every schedule entry + a shared cache lock
  (requires the externalised cache above).

Phase-12 already applies `->onOneServer()` where double-runs would be
harmful; verify coverage before scaling the fleet.

## Graceful shutdown

Instances are created and destroyed routinely under autoscaling — see
the "Graceful shutdown" section of
`docs/runbooks/queue-worker-config.md` for the full contract:

- queue workers finish their in-flight job on `SIGTERM`;
- `stopwaitsecs` / `TimeoutStopSec` must exceed the longest job timeout;
- the load balancer must drain connections before an instance dies;
- `php artisan down` renders the self-contained `errors/503.blade.php`.

## Horizontal-scale checklist

Before increasing the instance count:

1. `SESSION_DRIVER` + `CACHE_STORE` are redis — confirm the production
   validator log is clean of PERF-SCALE-1 warnings.
2. The scheduler runs on exactly one instance (dedicated instance or
   `onOneServer` coverage verified).
3. Graceful-shutdown timeouts are configured (`stopwaitsecs` > job
   `--timeout`).
4. Load-balancer connection draining is enabled.
5. Run `baseline.js` (`docs/runbooks/load-testing.md`) against the
   scaled topology — confirm latency improves with instance count and
   sessions survive hitting different instances.
6. **Accept the known gap**: uploaded files are not yet on a shared
   disk. Either the local-disk migration lands first, or the deploy
   uses a single instance for the upload-handling routes, or a shared
   network volume is mounted as `local`.

## See also

- `docs/runbooks/load-testing.md` — load-testing the topology
- `docs/runbooks/slo.md` — the latency budgets to hold under scale
- `docs/runbooks/queue-worker-config.md` — graceful shutdown + worker sizing
- `docs/runbooks/disaster-recovery.md` — backup/restore (Phase-12)
