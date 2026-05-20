<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Mail\StaleHoldReminderMailable;
use App\Models\AlertFiring;
use App\Models\Invoice;
use App\Models\LegalHold;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-68 STALE-SWEEP: holds active past the stale threshold emit the
 * gauge + fire the alert + nudge the owning landlord, at most once per
 * cooldown window. Fresh/released holds are ignored.
 */
class Phase68StaleSweepTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function holdInvoice(int $heldDaysAgo, ?\Illuminate\Support\Carbon $lastRemindedAt = null): LegalHold
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);

        return LegalHold::create([
            'holdable_type' => Invoice::class,
            'holdable_id' => $invoice->id,
            'reason' => 'preservation order CV/2024/0001',
            'held_by' => $this->landlord->id,
            'held_at' => now()->subDays($heldDaysAgo),
            'last_reminded_at' => $lastRemindedAt,
        ]);
    }

    public function test_stale_hold_emits_gauge_and_reminds_landlord(): void
    {
        Mail::fake();
        $spy = $this->spy(MetricsService::class);
        $hold = $this->holdInvoice(400);

        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();

        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name, float $value) => $name === 'legal_hold_stale_count' && abs($value - 1.0) < 0.0001,
        );
        Mail::assertQueued(
            StaleHoldReminderMailable::class,
            fn (StaleHoldReminderMailable $m) => $m->landlord->id === $this->landlord->id,
        );
        $this->assertNotNull($hold->fresh()->last_reminded_at);
    }

    public function test_fresh_hold_is_not_stale(): void
    {
        Mail::fake();
        $spy = $this->spy(MetricsService::class);
        $this->holdInvoice(10);

        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();

        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name, float $value) => $name === 'legal_hold_stale_count' && abs($value) < 0.0001,
        );
        Mail::assertNothingQueued();
    }

    public function test_cooldown_suppresses_repeat_reminder(): void
    {
        Mail::fake();
        // Stale, but reminded 5 days ago (< 30-day cooldown).
        $this->holdInvoice(400, now()->subDays(5));

        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();

        // Still counted as stale, but no fresh reminder within the cooldown.
        Mail::assertNothingQueued();
    }

    public function test_reminder_resent_after_cooldown(): void
    {
        Mail::fake();
        $this->holdInvoice(400, now()->subDays(45)); // last reminded > 30 days ago

        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();

        Mail::assertQueued(StaleHoldReminderMailable::class);
    }

    public function test_alert_fires_and_resolves(): void
    {
        Mail::fake();
        $this->holdInvoice(400);

        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();
        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'legal_hold_stale',
            'severity' => 'sev3',
            'resolved_at' => null,
        ]);

        // No stale holds remaining -> the alert resolves on the next run.
        LegalHold::query()->update(['released_at' => now(), 'released_by' => $this->landlord->id]);
        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();

        $firing = AlertFiring::where('alert_key', 'legal_hold_stale')->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_alert_registered_with_resolvable_runbook(): void
    {
        $alert = collect(config('alerts.alerts'))->firstWhere('key', 'legal_hold_stale');

        $this->assertNotNull($alert);
        $this->assertSame('sev3', $alert['severity']);
        $this->assertSame('legal_hold_stale_count', $alert['gauge']);
        $this->assertSame('docs/runbooks/legal-hold.md#stale-holds', $alert['runbook']);
    }
}
