<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Mail\BreachEscalationAlert;
use App\Mail\BreachReportedAlert;
use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-13 BREACH-1/3 regression coverage.
 *
 * Locks in:
 *   - dpa:initiate-breach creates a SecurityIncident + queues the
 *     ops alert mailable (BREACH-1)
 *   - breach:escalate-overdue pages for overdue and imminent incidents
 *     and writes a SecurityLog row each time (BREACH-3)
 *   - dpa:mark-regulator-notified stops the escalation loop
 */
class BreachNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_breach_command_dry_run_does_not_create_incident(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);

        Mail::fake();

        $exit = Artisan::call('dpa:initiate-breach', [
            '--description' => 'S3 listing exposed for 6h',
            '--data-types' => 'national_id,phone',
            '--affected' => 12,
            '--mitigation' => 'Bucket policy reverted; tokens rotated',
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(0, SecurityIncident::count());
        Mail::assertNothingQueued();
    }

    public function test_initiate_breach_requires_description_and_mitigation(): void
    {
        $exit = Artisan::call('dpa:initiate-breach', [
            '--confirm' => true,
        ]);

        // Command\Command::INVALID == 2
        $this->assertSame(2, $exit);
        $this->assertSame(0, SecurityIncident::count());
    }

    public function test_initiate_breach_with_confirm_creates_incident_and_queues_alert(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        $exit = Artisan::call('dpa:initiate-breach', [
            '--description' => 'S3 listing exposed for 6h',
            '--data-types' => 'national_id,phone',
            '--affected' => 12,
            '--mitigation' => 'Bucket policy reverted; tokens rotated',
            '--confirm' => true,
        ]);

        $this->assertSame(0, $exit);
        $incident = SecurityIncident::first();
        $this->assertNotNull($incident);
        $this->assertSame('data_breach', $incident->type);
        $this->assertEqualsWithDelta(
            now()->addHours(72)->timestamp,
            $incident->notification_deadline->timestamp,
            5,
            'notification_deadline must be 72h after creation',
        );

        Mail::assertQueued(BreachReportedAlert::class, function (BreachReportedAlert $mail) use ($incident) {
            return $mail->incident->is($incident)
                && $mail->hasTo('ops@example.test');
        });
    }

    public function test_initiate_breach_without_recipient_logs_warning_but_still_creates_incident(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => null]);
        Mail::fake();

        $exit = Artisan::call('dpa:initiate-breach', [
            '--description' => 'leak',
            '--mitigation' => 'rotation done',
            '--confirm' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(1, SecurityIncident::count());
        // No super_admin user → no admin email fan-out either.
        Mail::assertNothingQueued();
    }

    public function test_escalate_overdue_pages_overdue_incidents_and_writes_security_log(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        $overdue = SecurityIncident::factory()
            ->dataBreach()
            ->overdue()
            ->create();

        $exit = Artisan::call('breach:escalate-overdue');

        $this->assertSame(0, $exit);
        Mail::assertQueued(BreachEscalationAlert::class, function (BreachEscalationAlert $mail) use ($overdue) {
            return $mail->incident->is($overdue)
                && $mail->stage === 'overdue'
                && $mail->hasTo('ops@example.test');
        });

        $log = SecurityLog::where('event_type', 'breach_sla_overdue')->first();
        $this->assertNotNull($log, 'overdue escalation must write SecurityLog row');
        $this->assertSame(SecurityLog::SEVERITY_CRITICAL, $log->severity);
        $this->assertSame($overdue->id, $log->metadata['incident_id']);
    }

    public function test_escalate_overdue_pages_imminent_incidents_within_12h(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        $imminent = SecurityIncident::factory()
            ->dataBreach()
            ->state([
                'reported_at' => now()->subHours(66),
                'notification_deadline' => now()->addHours(6),
                'odpc_notified_at' => null,
            ])
            ->create();

        $exit = Artisan::call('breach:escalate-overdue');

        $this->assertSame(0, $exit);
        Mail::assertQueued(BreachEscalationAlert::class, function (BreachEscalationAlert $mail) use ($imminent) {
            return $mail->incident->is($imminent) && $mail->stage === 'imminent';
        });
        $this->assertNotNull(SecurityLog::where('event_type', 'breach_sla_imminent')->first());
    }

    public function test_escalate_overdue_ignores_already_notified_incidents(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        SecurityIncident::factory()
            ->dataBreach()
            ->overdue()
            ->odpcNotified()
            ->create();

        $exit = Artisan::call('breach:escalate-overdue');

        $this->assertSame(0, $exit);
        Mail::assertNothingQueued();
        $this->assertSame(0, SecurityLog::where('event_type', 'like', 'breach_sla_%')->count());
    }

    public function test_mark_regulator_notified_records_acknowledgement_and_stops_escalation(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        $incident = SecurityIncident::factory()
            ->dataBreach()
            ->overdue()
            ->create();

        $exit = Artisan::call('dpa:mark-regulator-notified', [
            '--incident' => $incident->id,
            '--reference' => 'ODPC/2026/12345',
            '--confirm' => true,
        ]);

        $this->assertSame(0, $exit);
        $incident->refresh();
        $this->assertNotNull($incident->odpc_notified_at, 'odpc_notified_at must be set');
        $this->assertSame(SecurityIncident::STATUS_INVESTIGATING, $incident->status);

        $log = SecurityLog::where('event_type', 'breach_regulator_notified')->first();
        $this->assertNotNull($log);
        $this->assertSame($incident->id, $log->metadata['incident_id']);
        $this->assertSame('ODPC/2026/12345', $log->metadata['reference']);

        // Now run the escalation — should be a no-op since the
        // incident is no longer pendingOdpcNotification.
        $exit = Artisan::call('breach:escalate-overdue');
        $this->assertSame(0, $exit);
        Mail::assertNothingQueued();
    }

    public function test_initiate_breach_via_service_pages_super_admins(): void
    {
        config(['security.kenya_dpa.breach_notification_email' => 'ops@example.test']);
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'super@example.test',
        ]);

        $dpa = app(\App\Services\KenyaDpaService::class);
        $incident = $dpa->initiateBreachNotification(
            breachDescription: 'leak',
            affectedDataTypes: ['phone'],
            estimatedAffectedUsers: 1,
            mitigationMeasures: 'rotation done',
        );

        Mail::assertQueued(BreachReportedAlert::class, fn ($mail) => $mail->hasTo('ops@example.test'));
        Mail::assertQueued(BreachReportedAlert::class, fn ($mail) => $mail->hasTo($admin->email));
        $this->assertNotNull($incident->id);
    }
}
