<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FileRetentionPolicy;
use Illuminate\Database\Seeder;

/**
 * Phase-59 FILE-RETENTION-1: seed 7 platform-default retention rows
 * (landlord_id NULL). Per-landlord overrides are created via the
 * landlord settings UI (not yet shipped — operator-managed today).
 *
 * Retention windows reflect Kenya DPA + landlord-tenant law:
 *   - lease_doc: 7yr  (rent disputes statute of limitations)
 *   - kyc_doc:   5yr  (DPA financial PII window)
 *   - invoice_pdf: 7yr (tax records)
 *   - water_reading_photo: 2yr (audit window after invoice issued)
 *   - export_zip: 7d  (Article-20 export self-service download window)
 *   - ocr_temp:   1d  (transient processing artefact)
 *   - file_access_audit: 90d (Phase-59 ACCESS-AUDIT-1 retention)
 */
class Phase59FileRetentionPolicySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['subject' => 'ocr_temp', 'retention_days' => 1],
            ['subject' => 'export_zip', 'retention_days' => 7],
            ['subject' => 'kyc_doc', 'retention_days' => 1825],
            ['subject' => 'lease_doc', 'retention_days' => 2555],
            ['subject' => 'water_reading_photo', 'retention_days' => 730],
            ['subject' => 'invoice_pdf', 'retention_days' => 2555],
            ['subject' => 'file_access_audit', 'retention_days' => 90],
        ];

        foreach ($defaults as $row) {
            FileRetentionPolicy::updateOrCreate(
                ['subject' => $row['subject'], 'landlord_id' => null],
                ['retention_days' => $row['retention_days']],
            );
        }
    }
}
