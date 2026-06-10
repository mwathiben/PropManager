<?php

namespace Tests\Unit\Services;

use App\Enums\InvoiceStatus;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit M0: WalletService is the single place the prepayment-balance
 * money invariant lives (credit / apply / applyToInvoice, with the
 * transaction + lockForUpdate guard). It had no dedicated test. These
 * cover the default-currency money paths: crediting, capped draws, and
 * applying a balance onto an invoice (partial vs paid + remainder).
 */
class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'name' => 'Wallet Test Property',
            'address' => '1 Wallet St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);
        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Block W',
            'total_floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $landlord->id,
            'building_type' => 'residential_apartment',
        ]);
        $unit = Unit::create([
            'building_id' => $building->id,
            'unit_number' => 'W1',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 10000,
            'landlord_id' => $landlord->id,
        ]);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $this->lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'rent_amount' => 10000,
            'deposit_amount' => 10000,
            'start_date' => now(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);
    }

    public function test_credit_increases_wallet_balance(): void
    {
        DB::transaction(fn () => $this->wallet->credit($this->lease, 1000.0, 'overpayment'));
        $this->assertSame(1000.0, $this->wallet->balanceFor($this->lease->fresh()));

        DB::transaction(fn () => $this->wallet->credit($this->lease, 500.0));
        $this->assertSame(1500.0, $this->wallet->balanceFor($this->lease->fresh()));
    }

    public function test_apply_is_capped_at_available_balance(): void
    {
        DB::transaction(fn () => $this->wallet->credit($this->lease, 100.0));

        $drawn = DB::transaction(fn () => $this->wallet->apply($this->lease, 500.0, 'overdraw attempt'));

        $this->assertSame(100.0, $drawn, 'apply must cap at the available balance, not overdraw');
        $this->assertSame(0.0, $this->wallet->balanceFor($this->lease->fresh()));
    }

    public function test_apply_to_invoice_is_partial_when_wallet_below_outstanding(): void
    {
        DB::transaction(fn () => $this->wallet->credit($this->lease, 400.0));
        $invoice = $this->makeInvoice(1000.0);

        $drawn = $this->wallet->applyToInvoice($invoice);

        $this->assertSame(400.0, $drawn);
        $invoice->refresh();
        $this->assertSame(400.0, (float) $invoice->amount_paid);
        $this->assertSame(400.0, (float) $invoice->wallet_applied);
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame(0.0, $this->wallet->balanceFor($this->lease->fresh()));
    }

    public function test_apply_to_invoice_marks_paid_and_keeps_remainder(): void
    {
        DB::transaction(fn () => $this->wallet->credit($this->lease, 500.0));
        $invoice = $this->makeInvoice(300.0);

        $drawn = $this->wallet->applyToInvoice($invoice);

        $this->assertSame(300.0, $drawn, 'only the outstanding amount is drawn, not the whole balance');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(200.0, $this->wallet->balanceFor($this->lease->fresh()), 'the unspent remainder stays in the wallet');
    }

    private function makeInvoice(float $totalDue): Invoice
    {
        return Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->lease->landlord_id,
            'invoice_number' => 'INV-WALLET-'.uniqid(),
            'due_date' => now()->addDays(5),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => $totalDue,
            'water_due' => 0,
            'arrears' => 0,
            'total_due' => $totalDue,
            'amount_paid' => 0,
            'wallet_applied' => 0,
            'status' => InvoiceStatus::Sent,
        ]);
    }
}
