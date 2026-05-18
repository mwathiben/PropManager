# Storage runbook

PropManager-wide file storage architecture. The HTTP-layer cache contract
lives in [cache.md](cache.md); this runbook covers the application
filesystem.

## Surface ownership

| Subject | Path columns | Lineage |
|---|---|---|
| `Document` | `Document.file_path` | Phase 28 TENANT-DOCS |
| `WaterReading` photo | `WaterReading.photo_path` | Phase 45 TICKET-PHOTOS lineage |
| `Lease` document | `Lease.lease_doc_path` | core schema |
| `Invoice` PDF | `Invoice.pdf_path` | core schema |
| `TenantKycSubmission` doc | per-submission storage | Phase 28 KYC |
| GDPR / data export ZIPs | `storage/app/exports/*` | core schema |
| OCR Tesseract working dir | `storage/app/ocr-temp/*` | core schema |

All seven flow through the **tenant disk** (Phase 58) — the actual
underlying driver is controlled by a single env var.

## The tenant disk (Phase 58)

`Storage::tenant(?int $landlordId = null)` is the single facade every
tenant-scoped file operation must use. It resolves to
`Storage::disk(config('filesystems.tenant_disk', 'local'))` via
`App\Services\Storage\TenantDiskResolver`.

```php
// Read
$contents = Storage::tenant()->get($document->file_path);

// Write
Storage::tenant()->put($path, $payload);

// Existence / delete
Storage::tenant()->exists($path);
Storage::tenant()->delete($path);

// Pass landlord_id when the caller has it — forward-compat with
// per-tenant disk routing.
Storage::tenant($lease->landlord_id)->download($lease->lease_doc_path);
```

Pre-Phase-58 the codebase had 28 hardcoded `Storage::disk('local')`
callsites — KYC docs, lease docs, water-reading photos, invoice PDFs,
GDPR exports, OCR temp files all bound to one host's disk. Phase 58
refactored every callsite to flow through `Storage::tenant()`.

## Disk catalog

| Disk name | Driver | Purpose |
|---|---|---|
| `local` | local (`storage/app/`) | Default for dev/test. Pre-Phase-58 production fallback. |
| `private` | local (`storage/app/private/`) | Phase-1 UPLOAD-5 alias-disk — sensitive uploads outside the public link tree. |
| `public` | local (`storage/app/public/`) | Web-accessible assets via `storage:link`. |
| `archive` | local (`storage/app/archive/`) | Phase 39 cold-storage rehydration target. |
| `s3` | s3 | Production target when `FILESYSTEM_TENANT_DISK=s3` is set. |
| `tenant` (logical) | resolves via `config('filesystems.tenant_disk')` | The Phase-58 facade. Routes through whichever underlying disk the operator picked. |

`tenant` is not a registered disk in `config/filesystems.php` — it's a
*logical* binding via the macro. The underlying physical disk is
whichever real disk `tenant_disk` points at.

## Operator workflow: flip to S3

The migration is **operator-only** — no code change, no DB migration.

1. **Provision the bucket** (out-of-band; standard AWS setup):
   - Create the bucket in your AWS account.
   - Set the bucket policy to require server-side encryption on PUT for
     KYC / lease document key prefixes. KYC docs are PII; encryption at
     rest is a compliance requirement.
   - Issue an IAM credential pair scoped to PUT/GET/DELETE on the bucket
     (no LIST on the bucket root if you can avoid it).

2. **Populate env vars** in production `.env`:
   ```
   AWS_ACCESS_KEY_ID=...
   AWS_SECRET_ACCESS_KEY=...
   AWS_DEFAULT_REGION=...
   AWS_BUCKET=...
   AWS_USE_PATH_STYLE_ENDPOINT=false
   ```
   These already drive the existing `s3` disk in
   `config/filesystems.php`.

3. **Set the tenant_disk knob**:
   ```
   FILESYSTEM_TENANT_DISK=s3
   ```

4. **Restart the application** (queue workers + web).

That's it. No DB migration — the path strings stored in
`Document.file_path` / `WaterReading.photo_path` /
`Lease.lease_doc_path` / `Invoice.pdf_path` stay the same; only the disk
used to access them changes.

If you need to backfill existing on-disk content to S3, that's a
one-time `aws s3 sync` from `storage/app/` to your bucket — outside the
scope of this runbook because the operation runs against the host's
filesystem directly, not through the Laravel Filesystem abstraction.

## The `path()` caveat

`Filesystem::path($relative)` returns an absolute filesystem path on the
local driver but **throws** on s3 — the concept doesn't apply remotely.
Two known call sites use `path()` for subprocess flows:

- `app/Services/DataExportService.php` line 90 — `ZipArchive::open()`
  needs a real path on disk.
- `app/Services/OcrService.php` line 288 — Tesseract subprocess reads
  from the file path.

When flipping to s3, both will need to download to a temp dir first
before the subprocess can read the file. Pattern:

```php
$temp = tempnam(sys_get_temp_dir(), 'pm-ocr-');
file_put_contents($temp, Storage::tenant()->get($relative));
try {
    // ... subprocess work using $temp ...
} finally {
    @unlink($temp);
}
```

This pattern is **not yet wired** because production is still on local.
Operator should add it as part of the s3 cutover ticket.

## Per-tenant routing (forward-compat)

`Storage::tenant(?int $landlordId = null)` accepts a landlord id today
but doesn't use it — the default resolver returns the same disk for
every tenant.

Future work (post-Phase-58) can swap `TenantDiskResolver::resolve()` for
a sharded variant: different bucket prefix per landlord, different
S3 region per landlord, or even a dedicated bucket per high-value
tenant. The public API stays identical; callsites that already pass
`landlord_id` are automatically opted in.

Callsites that pass `landlord_id` today:

- `app/Http/Controllers/DocumentController.php`
- `app/Http/Controllers/WaterReadingController.php`
- `app/Http/Controllers/LeaseController.php`
- `app/Http/Controllers/TenantDocumentsController.php`
- `app/Http/Controllers/TenantKycController.php`
- `app/Models/WaterReading.php` (via `$this->landlord_id`)

Callsites that pass null (no implicit landlord context):

- `app/Models/Document.php` (the model itself doesn't carry
  `landlord_id` — the User relation does)
- `app/Services/DataExportService.php` + `OcrService.php` + others
  operating on per-export / per-job paths

## Cache and invariants

| Invariant | Guarded by |
|---|---|
| Zero `Storage::disk('local')` callsites in `app/` | `Phase22StatelessnessTest` (shrink-only baseline = 0) + `Phase58CallsiteRefactorTest` |
| Every refactored file contains `Storage::tenant(` | `Phase58CallsiteRefactorTest` per-file presence check |
| `Storage::tenant()` macro registered | `Phase58SharedDiskMigrationSurfaceTest` |
| `TenantDiskResolver::resolve` is fail-soft to `local` on bad config | `Phase58TenantDiskResolverTest` |
| Round-trip put/get/exists/delete through tenant disk | `Phase58TenantDiskRoundTripTest` |
| `config('filesystems.tenant_disk')` exists | `Phase58TenantDiskResolverTest` + surface test |

## Lineage

- Phase 22 PERF-SCALE-1 — set the statelessness watchdog at 26 callsites
- Phase 28 TENANT-DOCS — added tenant-document upload callsites
- Phase 45 TICKET-PHOTOS — added ticket-annotation callsites (baseline bumped to 28)
- Phase 57 PERF-DEEP — opened A8 carry-over to migrate the 28 callsites
- Phase 58 SHARED-DISK-MIGRATION — ships `Storage::tenant()` facade + refactors all 28 callsites + drops baseline to 0
- **Phase 59 STORAGE-HARDENING** — production-hardens the surface: signed URLs, TempFileResolver, per-landlord routing, retention policies, access audit trail

## Phase 59 STORAGE-HARDENING

### Signed-URL pattern

`TenantDiskResolver::temporaryUrl($path, $landlordId, $expiresMinutes, $filename, $disposition)` is the single entry point for short-lived browser-direct download URLs. On a driver that supports presigned URLs (s3, gcs) it returns the native URL; on the local driver it falls back to a Laravel `signed` route at `/files/local-stream` that re-validates the signature before streaming.

```php
$url = app(TenantDiskResolver::class)
    ->temporaryUrl($document->file_path, $document->landlord_id, 5, $document->file_name);

return redirect()->away($url);
```

The `signed` middleware on `/files/local-stream` re-validates the round-trip — mutating `?path=` invalidates the signature. The recipient cannot traverse to a different file with the same token.

Five download/response callsites flow through this pattern: DocumentController::download + ::view, LeaseController::download, TenantDocumentsController::download, WaterReadingController::photo. MoveOutController::deductionPhoto remains on `Storage::disk('private')` as a documented exception (its photos were written through a different alias-disk and flipping the read without backing file migration would 404 existing photos).

### TempFileResolver for subprocess callers

`App\Services\Storage\TempFileResolver::for($relativePath, $landlordId): TempFileHandle` solves the path() caveat. On local the handle wraps the existing `Storage::tenant()->path()` return; on s3 it downloads contents to `sys_get_temp_dir() + UUID` and owns the cleanup.

```php
$handle = app(TempFileResolver::class)->for($relativePath);
try {
    runSubprocess($handle->path());
} finally {
    $handle->cleanup();
}
```

Wired into the 2 known path() callsites:
- `DataExportService:90` — ZipArchive::open() builds the zip at `$handle->path()`; if the handle is owned (s3 case), the resulting zip is uploaded back to the tenant disk via `Storage::tenant()->put()`. `exportUserData` now returns the tenant-disk-relative path (was absolute). Callers were updated to match.
- `OcrService:288` — Tesseract subprocess reads from `$handle->path()` in a try/finally.

### Per-landlord routing (PrefixedDisk decorator)

`config('filesystems.tenant_disk_prefix_template')` opts in to per-landlord path prefixing. Default null = Phase-58 behaviour preserved. Operator sets `FILESYSTEM_TENANT_DISK_PREFIX_TEMPLATE='{landlord_id}/'` (or `'tenants/{landlord_id}/'`) to shard storage under a per-tenant directory.

When set + caller passes `$landlordId`, `TenantDiskResolver::resolve` wraps the underlying disk in `PrefixedDisk` — every path method prepends the prefix; file/directory listings strip it on return so callers always see unprefixed paths.

**One-way contract**: enabling the prefix is one-way for newly-written files; path strings written before the prefix is enabled cannot be read through the prefixed disk. Coordinate a backfill BEFORE flipping the env var.

```bash
# One-time migration when flipping per-landlord prefix on in prod:
aws s3 sync s3://bucket/exports/ s3://bucket/42/exports/ --include "exports/42/*"
# (or equivalent per-landlord rewrite; coordinate per environment)
```

### File retention policies

`file_retention_policies` table holds 7 platform defaults (subject NULL landlord_id) reflecting Kenya DPA + landlord-tenant law:

| Subject | Days | Reasoning |
|---|---|---|
| `lease_doc` | 2555 | 7yr — rent disputes statute of limitations |
| `kyc_doc` | 1825 | 5yr — DPA financial PII window |
| `invoice_pdf` | 2555 | 7yr — tax records |
| `water_reading_photo` | 730 | 2yr — audit window after invoice issued |
| `export_zip` | 7 | 7d — Article-20 self-service download window |
| `ocr_temp` | 1 | 1d — transient processing artefact |
| `file_access_audit` | 90 | 90d — PII access trail retention |

Per-landlord overrides via `file_retention_policies` row with both `subject` and `landlord_id` populated. `FileRetentionPolicy::resolveFor($subject, $landlordId)` falls back to the platform default when no override exists.

`storage:enforce-retention` cron daily 02:30 Africa/Nairobi (after `dpa:enforce-retention` at 02:00) walks every `FileRetentionPolicy::SUBJECTS` entry. `--dry-run` flag logs candidates + emits `files_retention_dry_run_candidate_count{subject}` gauge for pre-purge validation; real runs emit `files_retention_purged_count{subject}` gauge.

### File access audit trail

`file_access_audits` polymorphic table — every PII-bearing download lands a row via `FileAccessRecorder::record`. Wired into DocumentController + WaterReadingController; expanding to LeaseController + TenantKycController is a one-line addition.

Recorder is fail-soft: persistence failure logs a warning but does NOT 500 the download. The contract is "make best-effort to audit" not "audit or fail."

`file-access:anomaly-audit` cron every 5 minutes queries the trailing 5-min window grouped by `(user_id, action)`; rows over the threshold (default 50, env-tunable via `FILE_ACCESS_ANOMALY_THRESHOLD`) emit `file_access_anomaly_count{action}` gauge — operator alerts at sev3.

### Operator workflow: hardening checklist

When flipping `FILESYSTEM_TENANT_DISK=s3` in production, also enable:

1. `FILESYSTEM_TENANT_DISK_PREFIX_TEMPLATE='{landlord_id}/'` if multi-tenant isolation is required at the bucket level.
2. Verify the `storage:enforce-retention` cron is firing (`php artisan schedule:list` should show it at 02:30).
3. Verify `file-access:anomaly-audit` is firing (every 5 minutes).
4. Tune `FILE_ACCESS_ANOMALY_THRESHOLD` after a week of observation; the default 50 in 5 minutes is intentionally generous.
5. Confirm signed-URL controllers return 302s, not 200 streamed bodies (a 200 means an old Phase-58 controller wasn't refactored).
