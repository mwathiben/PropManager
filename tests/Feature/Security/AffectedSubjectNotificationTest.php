<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Mail\BreachAffectedSubjectNotice;
use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\KenyaDpaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-13 BREACH-4 regression coverage. Article 34 / Kenya DPA
 * Section 43(2) requires the controller to notify affected subjects
 * when a breach is likely to result in high risk to their rights. The
 * coverage here locks in:
 *
 *   - notifyAffectedSubjects queues one mailable per resolved user
 *   - users_notified_at gets stamped (markUsersNotified)
 *   - a SecurityLog row preserves the user-id list + counts
 *   - dpa:notify-affected-subjects --confirm honours the same path
 *   - empty inputs are no-ops (do not stamp users_notified_at)
 */
class AffectedSubjectNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_affected_subjects_queues_mail_and_stamps_users_notified_at(): void
    {
        Mail::fake();
        $incident = SecurityIncident::factory()->dataBreach()->create();
        $alice = User::factory()->create(['email' => 'alice@example.test']);
        $bob = User::factory()->create(['email' => 'bob@example.test']);

        $dpa = app(KenyaDpaService::class);
        $queued = $dpa->notifyAffectedSubjects($incident, [$alice->id, $bob->id]);

        $this->assertSame(2, $queued);
        Mail::assertQueued(BreachAffectedSubjectNotice::class, fn ($mail) => $mail->hasTo('alice@example.test'));
        Mail::assertQueued(BreachAffectedSubjectNotice::class, fn ($mail) => $mail->hasTo('bob@example.test'));

        $incident->refresh();
        $this->assertNotNull($incident->users_notified_at);

        $log = SecurityLog::where('event_type', 'breach_subjects_notified')->first();
        $this->assertNotNull($log);
        $this->assertSame($incident->id, $log->metadata['incident_id']);
        $this->assertSame(2, $log->metadata['subject_count']);
        $this->assertSame([$alice->id, $bob->id], $log->metadata['user_ids']);
    }

    public function test_notify_affected_subjects_with_empty_list_is_a_no_op(): void
    {
        Mail::fake();
        $incident = SecurityIncident::factory()->dataBreach()->create();

        $dpa = app(KenyaDpaService::class);
        $queued = $dpa->notifyAffectedSubjects($incident, []);

        $this->assertSame(0, $queued);
        Mail::assertNothingQueued();
        $incident->refresh();
        $this->assertNull($incident->users_notified_at, 'an empty notify must not stamp users_notified_at');
        $this->assertSame(0, SecurityLog::where('event_type', 'breach_subjects_notified')->count());
    }

    public function test_notify_affected_subjects_skips_unknown_ids(): void
    {
        Mail::fake();
        $incident = SecurityIncident::factory()->dataBreach()->create();
        $emailed = User::factory()->create(['email' => 'reachable@example.test']);

        $dpa = app(KenyaDpaService::class);
        // 9_999_999 does not resolve to a User → silently dropped.
        $queued = $dpa->notifyAffectedSubjects($incident, [$emailed->id, 9_999_999]);

        $this->assertSame(1, $queued);
        Mail::assertQueuedCount(1);
    }

public function test_artisan_dry_run_does_not_queue_or_stamp(): void
    {
        Mail::fake();
        $incident = SecurityIncident::factory()->dataBreach()->create();
        $alice = User::factory()->create();

        $exit = Artisan::call('dpa:notify-affected-subjects', [
            '--incident' => $incident->id,
            '--user-ids' => (string) $alice->id,
        ]);

        $this->assertSame(0, $exit);
        Mail::assertNothingQueued();
        $incident->refresh();
        $this->assertNull($incident->users_notified_at);
    }

    public function test_artisan_with_confirm_queues_mailables(): void
    {
        Mail::fake();
        $incident = SecurityIncident::factory()->dataBreach()->create();
        $alice = User::factory()->create();

        $exit = Artisan::call('dpa:notify-affected-subjects', [
            '--incident' => $incident->id,
            '--user-ids' => (string) $alice->id,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $exit);
        Mail::assertQueued(BreachAffectedSubjectNotice::class);
        $incident->refresh();
        $this->assertNotNull($incident->users_notified_at);
    }

    public function test_artisan_rejects_missing_incident(): void
    {
        Mail::fake();
        $exit = Artisan::call('dpa:notify-affected-subjects', [
            '--incident' => 999999,
            '--user-ids' => '1,2,3',
            '--confirm' => true,
        ]);

        $this->assertSame(1, $exit);
        Mail::assertNothingQueued();
    }
}
