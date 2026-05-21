<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Mail\ScheduledReportDelivery;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-73 SCHEDULED-DEPTH: in-place edit of cadence + recipient and
 * pause/resume of a schedule. Cadence edits re-anchor next_due_at;
 * recipient-only edits must not slide it. Paused rows are skipped by the
 * send cron; resuming re-anchors next_due_at from now() (no backlog).
 */
class Phase73ScheduledDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function reportFor(User $owner): SavedReport
    {
        $this->actingAs($owner);

        return SavedReport::create([
            'name' => 'Rent report',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount'], 'filters' => [], 'group_by' => [], 'sort_by' => [], 'limit' => 50],
        ]);
    }

    private function schedule(User $owner, SavedReport $report, array $overrides = []): ScheduledReport
    {
        $this->actingAs($owner);

        return ScheduledReport::create(array_merge([
            'saved_report_id' => $report->id,
            'cadence' => 'weekly',
            'recipient_email' => $owner->email,
            'next_due_at' => now()->addMonths(6),
        ], $overrides));
    }

    public function test_update_changes_cadence_and_reanchors_next_due_at(): void
    {
        $report = $this->reportFor($this->landlord);
        $schedule = $this->schedule($this->landlord, $report, ['cadence' => 'weekly']);

        $this->actingAs($this->landlord)
            ->put(route('reports.scheduled.update', $schedule->id), [
                'cadence' => 'monthly',
                'recipient_email' => $this->landlord->email,
            ])
            ->assertRedirect();

        $fresh = $schedule->fresh();
        $this->assertSame('monthly', $fresh->cadence);
        // Re-anchored from now() → about a month out, not the original 6 months.
        $this->assertTrue($fresh->next_due_at->lessThan(now()->addMonths(2)));
        $this->assertTrue($fresh->next_due_at->greaterThan(now()->addWeeks(3)));
    }

    public function test_recipient_only_edit_does_not_slide_next_due_at(): void
    {
        $report = $this->reportFor($this->landlord);
        $original = now()->addMonths(6);
        $schedule = $this->schedule($this->landlord, $report, ['cadence' => 'weekly', 'next_due_at' => $original]);

        $this->actingAs($this->landlord)
            ->put(route('reports.scheduled.update', $schedule->id), [
                'cadence' => 'weekly',
                'recipient_email' => $this->landlord->email,
            ])
            ->assertRedirect();

        // Cadence unchanged → next_due_at stays put (still ~6 months out).
        $this->assertTrue($schedule->fresh()->next_due_at->greaterThan(now()->addMonths(5)));
    }

    public function test_update_rejects_a_third_party_recipient(): void
    {
        $report = $this->reportFor($this->landlord);
        $schedule = $this->schedule($this->landlord, $report);

        $this->actingAs($this->landlord)
            ->put(route('reports.scheduled.update', $schedule->id), [
                'cadence' => 'weekly',
                'recipient_email' => 'stranger@example.com',
            ])
            ->assertSessionHasErrors('recipient_email');

        $this->assertSame($this->landlord->email, $schedule->fresh()->recipient_email);
    }

    public function test_cannot_update_another_landlords_schedule(): void
    {
        $report = $this->reportFor($this->otherLandlord);
        $foreign = $this->schedule($this->otherLandlord, $report);

        // TenantScope filters the foreign row out of route-model binding
        // (404) before the controller's 403 fallback can fire — a stronger
        // "doesn't exist for you" response.
        $this->actingAs($this->landlord)
            ->put(route('reports.scheduled.update', $foreign->id), [
                'cadence' => 'monthly',
                'recipient_email' => $this->landlord->email,
            ])
            ->assertNotFound();

        $this->assertSame('weekly', $foreign->fresh()->cadence);
    }

    public function test_toggle_pause_pauses_then_resumes(): void
    {
        $report = $this->reportFor($this->landlord);
        $schedule = $this->schedule($this->landlord, $report);

        $this->actingAs($this->landlord)
            ->post(route('reports.scheduled.toggle-pause', $schedule->id))
            ->assertRedirect();
        $this->assertNotNull($schedule->fresh()->paused_at);

        $this->actingAs($this->landlord)
            ->post(route('reports.scheduled.toggle-pause', $schedule->id))
            ->assertRedirect();
        $resumed = $schedule->fresh();
        $this->assertNull($resumed->paused_at);
        // Resume re-anchors weekly cadence → about a week out, not 6 months.
        $this->assertTrue($resumed->next_due_at->lessThan(now()->addWeeks(2)));
    }

    public function test_cannot_toggle_pause_another_landlords_schedule(): void
    {
        $report = $this->reportFor($this->otherLandlord);
        $foreign = $this->schedule($this->otherLandlord, $report);

        $this->actingAs($this->landlord)
            ->post(route('reports.scheduled.toggle-pause', $foreign->id))
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->paused_at);
    }

    public function test_send_cron_skips_paused_schedules(): void
    {
        Mail::fake();
        $report = $this->reportFor($this->landlord);

        $active = $this->schedule($this->landlord, $report, ['next_due_at' => now()->subHour()]);
        $paused = $this->schedule($this->landlord, $report, [
            'next_due_at' => now()->subHour(),
            'paused_at' => now(),
        ]);

        $this->artisan('reports:send-scheduled')->assertExitCode(0);

        Mail::assertQueued(ScheduledReportDelivery::class, 1);
        $this->assertNotNull($active->fresh()->last_sent_at);
        $this->assertNull($paused->fresh()->last_sent_at);
    }
}
