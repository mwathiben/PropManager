<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Mail\ScheduledReportDelivery;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-27 BI-DELIVERY-2/3 watchdog: scheduled-reports lifecycle.
 *
 * Covers the migration shape, the artisan command sends mail and
 * advances next_due_at, the ScheduledController validator enforces
 * the PERSONAL-DATA-1 recipient allowlist (no third-party emails).
 */
class Phase27ScheduledDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->create([
            'role' => 'landlord',
            'email' => 'landlord@propmanager.test',
        ]);
    }

    public function test_scheduled_reports_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('scheduled_reports'));
        foreach (['landlord_id', 'saved_report_id', 'cadence', 'recipient_email', 'next_due_at', 'last_sent_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('scheduled_reports', $col));
        }
    }

    public function test_command_sends_due_reports_and_advances_next_due_at(): void
    {
        Mail::fake();

        $savedReport = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Arrears',
            'config' => [
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'limit' => 10,
            ],
        ]);

        $schedule = ScheduledReport::create([
            'landlord_id' => $this->landlord->id,
            'saved_report_id' => $savedReport->id,
            'cadence' => 'weekly',
            'recipient_email' => $this->landlord->email,
            'next_due_at' => Carbon::now()->subHour(), // due
        ]);

        $this->artisan('reports:send-scheduled')->assertExitCode(0);

        Mail::assertQueued(
            ScheduledReportDelivery::class,
            fn (ScheduledReportDelivery $mail) => $mail->hasTo($this->landlord->email)
                && $mail->schedule->id === $schedule->id,
        );

        $schedule->refresh();
        $this->assertNotNull($schedule->last_sent_at, 'BI-DELIVERY-2: command must stamp last_sent_at.');
        $this->assertTrue(
            $schedule->next_due_at->isFuture(),
            'BI-DELIVERY-2: next_due_at must advance past now() after a successful send.',
        );
    }

    public function test_command_skips_schedules_not_yet_due(): void
    {
        Mail::fake();

        $savedReport = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Future',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount'], 'limit' => 10],
        ]);
        ScheduledReport::create([
            'landlord_id' => $this->landlord->id,
            'saved_report_id' => $savedReport->id,
            'cadence' => 'weekly',
            'recipient_email' => $this->landlord->email,
            'next_due_at' => Carbon::now()->addWeek(),
        ]);

        $this->artisan('reports:send-scheduled')->assertExitCode(0);
        Mail::assertNothingQueued();
    }

    public function test_dry_run_does_not_queue_mail(): void
    {
        Mail::fake();

        $savedReport = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Dry',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount'], 'limit' => 10],
        ]);
        ScheduledReport::create([
            'landlord_id' => $this->landlord->id,
            'saved_report_id' => $savedReport->id,
            'cadence' => 'weekly',
            'recipient_email' => $this->landlord->email,
            'next_due_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('reports:send-scheduled --dry-run')->assertExitCode(0);
        Mail::assertNothingQueued();
    }

    public function test_recipient_list_rejects_third_party_emails(): void
    {
        $savedReport = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
        ]);

        $this->actingAs($this->landlord)
            ->post(route('reports.scheduled.store'), [
                'saved_report_id' => $savedReport->id,
                'cadence' => 'weekly',
                'recipient_email' => 'attacker@evil.test',
            ])
            ->assertSessionHasErrors('recipient_email');

        $this->assertDatabaseCount('scheduled_reports', 0);
    }

    public function test_recipient_list_accepts_landlord_own_email(): void
    {
        $savedReport = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
        ]);

        $this->actingAs($this->landlord)
            ->post(route('reports.scheduled.store'), [
                'saved_report_id' => $savedReport->id,
                'cadence' => 'weekly',
                'recipient_email' => $this->landlord->email,
            ])
            ->assertRedirect(route('reports.scheduled.index'));

        $this->assertDatabaseCount('scheduled_reports', 1);
    }

    public function test_recipient_list_accepts_known_caretaker_email(): void
    {
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
            'email' => 'caretaker@propmanager.test',
        ]);

        $savedReport = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
        ]);

        $this->actingAs($this->landlord)
            ->post(route('reports.scheduled.store'), [
                'saved_report_id' => $savedReport->id,
                'cadence' => 'weekly',
                'recipient_email' => $caretaker->email,
            ])
            ->assertRedirect(route('reports.scheduled.index'));

        $this->assertDatabaseCount('scheduled_reports', 1);
    }

    public function test_scheduled_index_renders_inertia(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('reports.scheduled.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Scheduled')
                ->has('schedules')
                ->has('savedReports')
                ->has('cadences')
                ->has('allowedRecipients'),
            );
    }

    public function test_landlord_cannot_schedule_other_landlords_report(): void
    {
        $other = User::factory()->create(['role' => 'landlord']);
        $foreign = SavedReport::create([
            'landlord_id' => $other->id,
            'name' => 'Foreign',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
        ]);

        $this->actingAs($this->landlord)
            ->post(route('reports.scheduled.store'), [
                'saved_report_id' => $foreign->id,
                'cadence' => 'weekly',
                'recipient_email' => $this->landlord->email,
            ])
            ->assertSessionHasErrors('saved_report_id');
    }

    public function test_console_schedule_registers_send_scheduled(): void
    {
        $schedule = (string) file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString(
            'reports:send-scheduled',
            $schedule,
            'BI-DELIVERY-2: routes/console.php must Schedule::command("reports:send-scheduled").',
        );
        $this->assertStringContainsString(
            "dailyAt('06:00')",
            $schedule,
            'BI-DELIVERY-2: schedule must fire dailyAt("06:00").',
        );
    }
}
