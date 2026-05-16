<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Enums\InvoiceStatus;
use App\Models\EvictionNoticeDraft;
use App\Models\Invoice;
use App\Models\LandlordTask;
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
 * Phase-29 WF-LATE-FEE-1/2/3 watchdog suite.
 */
class Phase29LateFeeEscalationTest extends TestCase
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
    }

    public function test_day_5_fires_arrears_sms_reminder(): void
    {
        $this->overdueInvoice(5);
        $spy = $this->spyNotificationService();

        $this->artisan('invoices:escalate-overdue')->assertSuccessful();

        $spy->shouldHaveReceived('send')->once();
    }

    public function test_day_10_creates_landlord_call_task(): void
    {
        $invoice = $this->overdueInvoice(10);
        $this->artisan('invoices:escalate-overdue')->assertSuccessful();

        $task = LandlordTask::where('landlord_id', $this->landlord->id)
            ->where('related_to_id', $invoice->id)
            ->firstOrFail();

        $this->assertSame('overdue_invoice_call', $task->task_type);
        $this->assertSame('high', $task->priority);
        $this->assertSame('WF-LATE-FEE-1', $task->source_workflow);
        $this->assertSame(Invoice::class, $task->related_to_type);
    }

    public function test_day_30_creates_eviction_notice_draft_never_sent(): void
    {
        $invoice = $this->overdueInvoice(30);
        $this->artisan('invoices:escalate-overdue')->assertSuccessful();

        $draft = EvictionNoticeDraft::where('lease_id', $this->lease->id)->firstOrFail();
        $this->assertSame(EvictionNoticeDraft::STATUS_DRAFT, $draft->status);
        $this->assertNull($draft->sent_at);
        $this->assertContains($invoice->id, $draft->related_invoice_ids);
        $this->assertStringContainsString($this->tenant->name, $draft->draft_body);
        $this->assertStringContainsString('DRAFT', $draft->draft_body);
    }

    public function test_non_bucket_day_does_not_escalate(): void
    {
        $this->overdueInvoice(7);
        $spy = $this->spyNotificationService();

        $this->artisan('invoices:escalate-overdue')->assertSuccessful();

        $spy->shouldNotHaveReceived('send');
        $this->assertSame(0, LandlordTask::count());
        $this->assertSame(0, EvictionNoticeDraft::count());
    }

    public function test_paid_invoice_is_skipped(): void
    {
        $invoice = $this->overdueInvoice(10);
        // Pay it.
        $invoice->update(['status' => InvoiceStatus::Paid, 'amount_paid' => $invoice->total_due]);
        $spy = $this->spyNotificationService();

        $this->artisan('invoices:escalate-overdue')->assertSuccessful();

        $spy->shouldNotHaveReceived('send');
        $this->assertSame(0, LandlordTask::count());
    }

    public function test_idempotency_blocks_second_run_within_lock_window(): void
    {
        $this->overdueInvoice(10);

        $this->artisan('invoices:escalate-overdue')->assertSuccessful();
        $this->artisan('invoices:escalate-overdue')->assertSuccessful();

        $this->assertSame(1, LandlordTask::count(), 'second run must not duplicate task');
    }

    public function test_dry_run_creates_nothing(): void
    {
        $this->overdueInvoice(30);
        $spy = $this->spyNotificationService();

        $this->artisan('invoices:escalate-overdue', ['--dry-run' => true])->assertSuccessful();

        $spy->shouldNotHaveReceived('send');
        $this->assertSame(0, LandlordTask::count());
        $this->assertSame(0, EvictionNoticeDraft::count());
    }

    public function test_schedule_includes_invoices_escalate_overdue_at_00_30(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'invoices:escalate-overdue'));

        $this->assertNotNull($entry);
        $this->assertSame('30 0 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    private function overdueInvoice(int $daysOverdue): Invoice
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'overdue');
        $invoice->update(['due_date' => CarbonImmutable::now()->subDays($daysOverdue)->toDateString()]);

        return $invoice->fresh();
    }

    private function spyNotificationService(): \Mockery\MockInterface
    {
        $spy = Mockery::spy(NotificationService::class);
        $this->app->instance(NotificationService::class, $spy);

        return $spy;
    }
}
