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
- **Phase 58 SHARED-DISK-MIGRATION** — ships `Storage::tenant()` facade + refactors all 28 callsites + drops baseline to 0
