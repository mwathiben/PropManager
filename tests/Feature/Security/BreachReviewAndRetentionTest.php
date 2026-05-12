<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Consent;
use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\KenyaDpaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-13 BREACH-7 + DPA-8 coverage.
 *
 *   - SecurityIncident creation stamps review_due_at = +30d
 *   - breach:review-overdue surfaces overdue incidents + writes log
 *   - dpa:mark-review-complete stamps review_completed_at
 *   - logs:prune --table=consent purges withdrawn consents past 3y
 *     but leaves active consents alone
 */
class BreachReviewAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_breach_stamps_review_due_at_thirty_days_out(): void
    {
        $dpa = app(KenyaDpaService::class);
        $incident = $dpa->initiateBreachNotification(
            breachDescription: 'leak',
            affectedDataTypes: ['phone'],
            estimatedAffectedUsers: 1,
            mitigationMeasures: 'rotated',
        );

        $this->assertNotNull($incident->review_due_at);
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            $incident->review_due_at->timestamp,
            5,
            'review_due_at must be 30 days after creation',
        );
    }

    public function test_review_overdue_surfaces_old_incidents_and_writes_log(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        SecurityIncident::factory()->dataBreach()->create([
            'review_due_at' => now()->subDays(5),
            'review_completed_at' => null,
        ]);

        $exit = Artisan::call('breach:review-overdue');

        $this->assertSame(0, $exit);
        $log = SecurityLog::where('event_type', 'breach_review_overdue_batch')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, count($log->metadata['incident_ids']));
    }

    public function test_review_overdue_ignores_incidents_with_review_completed(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        SecurityIncident::factory()->dataBreach()->create([
            'review_due_at' => now()->subDays(5),
            'review_completed_at' => now()->subDay(),
        ]);

        $exit = Artisan::call('breach:review-overdue');

        $this->assertSame(0, $exit);
        $this->assertSame(0, SecurityLog::where('event_type', 'breach_review_overdue_batch')->count());
    }

    public function test_mark_review_complete_records_acknowledgement(): void
    {
        $incident = SecurityIncident::factory()->dataBreach()->create([
            'review_due_at' => now()->subDay(),
        ]);

        $exit = Artisan::call('dpa:mark-review-complete', [
            '--incident' => $incident->id,
            '--notes' => 'wiki://breach/2026-05',
            '--confirm' => true,
        ]);

        $this->assertSame(0, $exit);
        $incident->refresh();
        $this->assertNotNull($incident->review_completed_at);

        $log = SecurityLog::where('event_type', 'breach_review_complete')->first();
        $this->assertNotNull($log);
        $this->assertSame('wiki://breach/2026-05', $log->metadata['notes']);
    }

    public function test_logs_prune_consent_purges_withdrawn_past_three_years(): void
    {
        $user = User::factory()->create();
        $old = Consent::record($user, Consent::TYPE_MARKETING, '1.0');
        $old->update([
            'is_granted' => false,
            'withdrawn_at' => now()->subYears(4),
        ]);

        $recent = Consent::record($user, Consent::TYPE_DATA_PROCESSING, '1.0');
        $recent->update([
            'is_granted' => false,
            'withdrawn_at' => now()->subMonths(6),
        ]);

        $active = Consent::record($user, Consent::TYPE_THIRD_PARTY_SHARING, '1.0');

        $exit = Artisan::call('logs:prune', [
            '--table' => 'consent',
            '--confirm' => true,
        ]);

        $this->assertSame(0, $exit);

        $this->assertNull(
            DB::table('consents')->find($old->id),
            'consents withdrawn >3y ago must be pruned',
        );
        $this->assertNotNull(
            DB::table('consents')->find($recent->id),
            'consents withdrawn <3y ago must be retained',
        );
        $this->assertNotNull(
            DB::table('consents')->find($active->id),
            'active consents must never be pruned',
        );
    }
}
