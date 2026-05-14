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

## Known gap: hardcoded local-disk call sites

`Phase22StatelessnessTest` pins a shrink-only baseline of **26**
`Storage::disk('local')` call sites across `app/`. These hardcode the
local disk instead of the configurable default disk, so even with
`FILESYSTEM_DISK=s3` set, these paths still write to one host:

- KYC documents, lease documents (DocumentController, LeaseController,
  TenantKycController)
- water-reading photos (WaterReadingController, WaterReading model)
- invoice PDFs (GenerateInvoicePdf, InvoicePdfService)
- GDPR data exports (DataExportService)
- CSV imports (ImportService), OCR temp files (OcrService)

**This is the single biggest horizontal-scale blocker.** Migrating
these to the configurable shared disk is a tracked follow-up — it is
larger than a single Phase-22 finding and deserves its own work item.
Until it lands, a multi-instance deploy will have documents that exist
on only one instance. The watchdog guarantees the footprint only ever
shrinks.

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
