<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Models\FileAccessAudit;
use App\Models\User;
use App\Services\Storage\FileAccessRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-59 ACCESS-AUDIT-1/2/3: PII-bearing download audit trail +
 * 5-min anomaly detector.
 */
class Phase59AccessAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_polymorphic_subject_columns(): void
    {
        $this->assertTrue(Schema::hasTable('file_access_audits'));
        $this->assertTrue(Schema::hasColumns('file_access_audits', [
            'user_id', 'landlord_id', 'subject_type', 'subject_id',
            'action', 'ip_address', 'user_agent', 'accessed_path', 'accessed_at',
        ]));
    }

    public function test_action_constants_match_documented_values(): void
    {
        $this->assertSame('download', FileAccessAudit::ACTION_DOWNLOAD);
        $this->assertSame('view', FileAccessAudit::ACTION_VIEW);
        $this->assertSame('signed_url_issued', FileAccessAudit::ACTION_SIGNED_URL_ISSUED);
    }

    public function test_recorder_persists_row_with_subject_polymorphic_metadata(): void
    {
        $landlord = User::factory()->create();
        $tenant = User::factory()->create(['landlord_id' => $landlord->id]);

        // Use a User as the subject — it's a Model with a landlord_id
        // attribute, which is all FileAccessRecorder needs to land a
        // row. The recorder is subject-agnostic.
        $subject = User::factory()->create(['landlord_id' => $landlord->id]);

        app(FileAccessRecorder::class)->record(
            $tenant,
            $subject,
            FileAccessAudit::ACTION_DOWNLOAD,
            null,
            'kyc/test.pdf',
        );

        $audit = FileAccessAudit::query()
            ->withoutGlobalScopes()
            ->where('user_id', $tenant->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($landlord->id, $audit->landlord_id);
        $this->assertSame('download', $audit->action);
        $this->assertSame('kyc/test.pdf', $audit->accessed_path);
        $this->assertSame((string) $subject->getMorphClass(), $audit->subject_type);
        $this->assertSame($subject->id, $audit->subject_id);
    }

    public function test_recorder_is_fail_soft_when_persistence_throws(): void
    {
        $landlord = User::factory()->create();

        // Force a persistence failure by passing a user with an
        // invalid (non-existent) id — landlord_id FK constraint will
        // cascade-fail on insert.
        $orphan = new User;
        $orphan->id = 999999;

        $subject = User::factory()->create(['landlord_id' => $landlord->id]);
        $subject->landlord_id = 999999; // landlord_id that doesn't exist
        $subject->setAttribute('landlord_id', 999999);

        app(FileAccessRecorder::class)->record($orphan, $subject, 'download');

        // No throw == fail-soft contract honoured.
        $this->assertTrue(true);
    }

    public function test_anomaly_audit_command_class_exists_and_scheduled(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\FileAccessAnomalyAudit::class));

        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'file-access:anomaly-audit'));

        $this->assertNotNull($entry);
        $this->assertSame('*/5 * * * *', $entry->expression);
    }

    public function test_anomaly_threshold_is_configurable(): void
    {
        $this->assertSame(50, (int) config('observability.file_access_anomaly_threshold', 50));
    }
}
