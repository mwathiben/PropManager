<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Models\NotificationPreference;
use App\Models\RentReminderPolicy;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-29 WF-RENT-REMIND-1/2/3 watchdog suite.
 */
class Phase29RentReminderTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private $lease;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        RentReminderPolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Standard',
            'cadence_template' => 'standard',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_offset_zero_fires_when_invoice_due_today(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        $spy->shouldHaveReceived('send')->once();
    }

    public function test_offset_plus_three_fires_three_days_after_due_date(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->subDays(3)->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        $spy->shouldHaveReceived('send')->once();
    }

    public function test_offset_not_matched_does_not_fire(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->addDays(1)->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        $spy->shouldNotHaveReceived('send');
    }

    public function test_idempotency_prevents_double_fire_on_re_run(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        $spy->shouldHaveReceived('send')->once();
    }

    public function test_dry_run_does_not_call_notification_service(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch', ['--dry-run' => true])->assertSuccessful();

        $spy->shouldNotHaveReceived('send');
    }

    public function test_lease_tier_aggressive_picks_matching_policy_when_present(): void
    {
        RentReminderPolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Aggressive',
            'cadence_template' => 'aggressive',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->lease->update(['reminder_tier' => 'aggressive']);
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->addDays(3)->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        // Aggressive offsets include -3 which matches due in 3 days.
        $spy->shouldHaveReceived('send')->once();
    }

    public function test_lease_tier_without_matching_policy_falls_back_to_default(): void
    {
        $this->lease->update(['reminder_tier' => 'aggressive']);
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        // No aggressive policy exists; default (standard) has offset 0
        // which matches due today.
        $spy->shouldHaveReceived('send')->once();
    }

    public function test_custom_template_uses_offsets_json_verbatim(): void
    {
        RentReminderPolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Custom 14d',
            'cadence_template' => 'custom',
            'offsets_json' => [14],
            'is_default' => false,
            'is_active' => true,
        ]);
        $this->lease->update(['reminder_tier' => 'custom']);
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->subDays(14)->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        $spy->shouldHaveReceived('send')->once();
    }

    public function test_paid_invoice_does_not_receive_reminder(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'paid');
        $invoice->update(['due_date' => CarbonImmutable::now()->toDateString()]);

        $spy = $this->spyNotificationService();
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        $spy->shouldNotHaveReceived('send');
    }

    public function test_tenant_who_disabled_rent_reminder_receives_nothing(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['due_date' => CarbonImmutable::now()->toDateString()]);

        // Tenant disables rent_reminder type entirely.
        NotificationPreference::getOrCreate($this->tenant->id, $this->landlord->id)
            ->update(['rent_reminder_enabled' => false]);

        // Use the REAL NotificationService here (not a spy) so we can
        // assert the preference path actually blocks dispatch.
        $this->artisan('rent-reminders:dispatch')->assertSuccessful();

        $this->assertSame(
            0,
            \App\Models\Notification::where('recipient_id', $this->tenant->id)
                ->where('type', 'rent_reminder')
                ->count(),
            'rent_reminder must not be persisted when tenant disabled the type',
        );
    }

    public function test_schedule_includes_rent_reminders_dispatch_at_08_00_nairobi(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'rent-reminders:dispatch'));

        $this->assertNotNull($entry, 'rent-reminders:dispatch must be scheduled');
        $this->assertSame('0 8 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    private function spyNotificationService(): \Mockery\MockInterface
    {
        $spy = Mockery::spy(NotificationService::class);
        $this->app->instance(NotificationService::class, $spy);

        return $spy;
    }
}
