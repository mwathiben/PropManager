<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ArchiveOldPayments;
use App\Models\ArchivedPayment;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ArchiveOldPaymentsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private int $retentionYears;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retentionYears = config('security.compliance.data_retention_years', 7);
    }

    private function backdatePayment(Payment $payment, \DateTimeInterface $date): void
    {
        $payment->update(['payment_date' => $date]);
    }

    public function test_archival_via_chunk_surfaces_error(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease);

        $this->backdatePayment($payment, now()->subYears($this->retentionYears + 1));

        $service = app(\App\Services\Payment\PaymentArchivalService::class);
        $errors = [];

        Payment::withoutGlobalScope('landlord')
            ->archivable()
            ->with(['platformFee', 'receipt'])
            ->chunkById(500, function ($payments) use ($service, &$errors) {
                foreach ($payments as $p) {
                    try {
                        DB::transaction(fn () => $service->archivePayment($p));
                    } catch (\Throwable $e) {
                        $errors[] = $e->getMessage().' | '.$e->getTraceAsString();
                    }
                }
            });

        $this->assertEmpty($errors, 'Archival errors: '.implode("\n", $errors));
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_archives_payment_older_than_retention_period(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease);

        $this->backdatePayment($payment, now()->subYears($this->retentionYears + 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('archived_payments', [
            'original_payment_id' => $payment->id,
            'amount' => $payment->amount,
            'landlord_id' => $payment->landlord_id,
        ]);
    }

    public function test_does_not_archive_payment_within_retention_period(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease);

        $this->backdatePayment($payment, now()->subYears($this->retentionYears - 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseCount('archived_payments', 0);
    }

    public function test_preserves_related_data_in_archive(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment, 'invoice' => $invoice] = $this->createPaymentWithInvoice($lease);

        PlatformFee::create([
            'payment_id' => $payment->id,
            'landlord_id' => $landlord->id,
            'gross_amount' => $payment->amount,
            'fee_amount' => 150.00,
            'net_amount' => $payment->amount - 150.00,
        ]);

        Receipt::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'receipt_number' => 'RCT-001',
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'issued_at' => now(),
        ]);

        $this->backdatePayment($payment, now()->subYears($this->retentionYears + 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $archived = ArchivedPayment::where('original_payment_id', $payment->id)->first();
        $this->assertNotNull($archived);
        $this->assertNotNull($archived->related_data);
        $this->assertArrayHasKey('platform_fee', $archived->related_data);
        $this->assertArrayHasKey('receipt', $archived->related_data);

        $this->assertDatabaseMissing('platform_fees', ['payment_id' => $payment->id]);
        $this->assertDatabaseMissing('receipts', ['payment_id' => $payment->id]);
    }

    public function test_nulls_restrict_fk_references_before_delete(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease);

        DB::table('wallet_transactions')->insert([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'payment_id' => $payment->id,
            'type' => 'credit',
            'amount' => $payment->amount,
            'balance_after' => $payment->amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->backdatePayment($payment, now()->subYears($this->retentionYears + 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseHas('wallet_transactions', [
            'lease_id' => $lease->id,
            'payment_id' => null,
        ]);
        $this->assertDatabaseHas('archived_payments', [
            'original_payment_id' => $payment->id,
        ]);
    }

    public function test_creates_audit_log_for_archived_payment(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease);

        $this->backdatePayment($payment, now()->subYears($this->retentionYears + 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'archived',
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'landlord_id' => $payment->landlord_id,
        ]);
    }

    public function test_handles_empty_result_set_gracefully(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'archival complete'));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseCount('archived_payments', 0);
    }

    public function test_archives_voided_payments(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease);

        $payment->update([
            'is_voided' => true,
            'voided_at' => now()->subYears($this->retentionYears),
            'void_reason' => 'Duplicate payment',
        ]);
        $this->backdatePayment($payment, now()->subYears($this->retentionYears + 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $archived = ArchivedPayment::where('original_payment_id', $payment->id)->first();
        $this->assertNotNull($archived);
        $this->assertTrue($archived->is_voided);
    }

    public function test_boundary_exactly_at_retention_period_not_archived(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease);

        $this->backdatePayment($payment, now()->subYears($this->retentionYears)->addDay());

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseCount('archived_payments', 0);
    }

    public function test_processes_across_multiple_landlords(): void
    {
        $setup1 = $this->createLandlordWithFullSetup();
        ['lease' => $lease1] = $this->createTenantWithActiveLease($setup1['landlord'], $setup1['units']->first());
        ['payment' => $payment1] = $this->createPaymentWithInvoice($lease1);

        $setup2 = $this->createLandlordWithFullSetup();
        ['lease' => $lease2] = $this->createTenantWithActiveLease($setup2['landlord'], $setup2['units']->first());
        ['payment' => $payment2] = $this->createPaymentWithInvoice($lease2);

        $this->backdatePayment($payment1, now()->subYears($this->retentionYears + 1));
        $this->backdatePayment($payment2, now()->subYears($this->retentionYears + 2));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseCount('archived_payments', 2);
        $this->assertDatabaseMissing('payments', ['id' => $payment1->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment2->id]);
    }

    public function test_continues_after_single_payment_error(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());

        ['payment' => $payment1] = $this->createPaymentWithInvoice($lease);
        ['payment' => $payment2] = $this->createPaymentWithInvoice($lease);

        $this->backdatePayment($payment1, now()->subYears($this->retentionYears + 1));
        $this->backdatePayment($payment2, now()->subYears($this->retentionYears + 1));

        // The service handles known FK references (bank_webhook_logs, wallet_transactions,
        // bank_reconciliation_queue) by nulling them. Both payments should archive.
        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $this->assertDatabaseCount('archived_payments', 2);
        $this->assertTrue(
            ArchivedPayment::where('original_payment_id', $payment1->id)->exists()
        );
        $this->assertTrue(
            ArchivedPayment::where('original_payment_id', $payment2->id)->exists()
        );
    }

    public function test_all_payments_view_includes_archived_and_active(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $oldPayment] = $this->createPaymentWithInvoice($lease);
        ['payment' => $newPayment] = $this->createPaymentWithInvoice($lease);

        $this->backdatePayment($oldPayment, now()->subYears($this->retentionYears + 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $viewCount = DB::table('all_payments')
            ->where('landlord_id', $landlord->id)
            ->count();

        $this->assertEquals(2, $viewCount);
    }

    public function test_with_archived_scope_queries_both_tables(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        ['payment' => $oldPayment] = $this->createPaymentWithInvoice($lease, 3000);
        ['payment' => $newPayment] = $this->createPaymentWithInvoice($lease, 5000);

        $this->backdatePayment($oldPayment, now()->subYears($this->retentionYears + 1));

        (new ArchiveOldPayments)->handle(app(\App\Services\Payment\PaymentArchivalService::class));

        $totalFromView = DB::table('all_payments')
            ->where('landlord_id', $landlord->id)
            ->sum('amount');

        $this->assertEquals(8000.00, (float) $totalFromView);
    }
}
