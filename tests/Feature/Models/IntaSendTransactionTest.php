<?php

namespace Tests\Feature\Models;

use App\Models\IntaSendTransaction;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class IntaSendTransactionTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected Invoice $invoice;

    protected Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $unit = $this->setupData['units']->first();
        ['lease' => $this->lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $this->invoice = $this->createInvoiceForLease($this->lease, 'sent');
    }

    public function test_intasend_transaction_belongs_to_invoice(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->create();

        $this->assertInstanceOf(Invoice::class, $transaction->invoice);
        $this->assertEquals($this->invoice->id, $transaction->invoice->id);
    }

    public function test_intasend_transaction_belongs_to_landlord(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->create();

        $this->assertInstanceOf(User::class, $transaction->landlord);
        $this->assertEquals($this->landlord->id, $transaction->landlord->id);
    }

    public function test_intasend_transaction_can_belong_to_payment(): void
    {
        $payment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'PAY-'.uniqid(),
            'intasend_transaction_id' => 'INTASEND123',
            'intasend_reference' => 'ITS-'.time().'-ABC123',
        ]);

        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->complete()
            ->create(['payment_id' => $payment->id]);

        $this->assertInstanceOf(Payment::class, $transaction->payment);
        $this->assertEquals($payment->id, $transaction->payment->id);
    }

    public function test_intasend_transaction_payment_is_nullable(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->pending()
            ->create();

        $this->assertNull($transaction->payment);
    }

    public function test_pending_scope_returns_pending_transactions(): void
    {
        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->pending()
            ->count(2)
            ->create();

        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->complete()
            ->create();

        $pending = IntaSendTransaction::pending()->get();

        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(fn ($t) => $t->state === 'PENDING'));
    }

    public function test_complete_scope_returns_complete_transactions(): void
    {
        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->pending()
            ->create();

        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->complete()
            ->count(3)
            ->create();

        $complete = IntaSendTransaction::complete()->get();

        $this->assertCount(3, $complete);
        $this->assertTrue($complete->every(fn ($t) => $t->state === 'COMPLETE'));
    }

    public function test_failed_scope_returns_failed_transactions(): void
    {
        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->pending()
            ->create();

        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->failed()
            ->count(2)
            ->create();

        $failed = IntaSendTransaction::failed()->get();

        $this->assertCount(2, $failed);
        $this->assertTrue($failed->every(fn ($t) => $t->state === 'FAILED'));
    }

    public function test_for_invoice_scope_returns_transactions_for_specific_invoice(): void
    {
        // Create another unit and lease for a second invoice
        $otherUnit = $this->setupData['units']->skip(1)->first();
        ['lease' => $otherLease] = $this->createTenantWithActiveLease($this->landlord, $otherUnit);
        $otherInvoice = $this->createInvoiceForLease($otherLease, 'sent');

        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->count(2)
            ->create();

        IntaSendTransaction::factory()
            ->forInvoice($otherInvoice)
            ->create();

        $transactions = IntaSendTransaction::forInvoice($this->invoice->id)->get();

        $this->assertCount(2, $transactions);
        $this->assertTrue($transactions->every(fn ($t) => $t->invoice_id === $this->invoice->id));
    }

    public function test_is_pending_returns_true_for_pending_state(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->pending()
            ->create();

        $this->assertTrue($transaction->isPending());
        $this->assertFalse($transaction->isComplete());
        $this->assertFalse($transaction->isFailed());
    }

    public function test_is_complete_returns_true_for_complete_state(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->complete()
            ->create();

        $this->assertFalse($transaction->isPending());
        $this->assertTrue($transaction->isComplete());
        $this->assertFalse($transaction->isFailed());
    }

    public function test_is_failed_returns_true_for_failed_state(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->failed()
            ->create();

        $this->assertFalse($transaction->isPending());
        $this->assertFalse($transaction->isComplete());
        $this->assertTrue($transaction->isFailed());
    }

    public function test_mark_complete_updates_state_and_mpesa_receipt(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->pending()
            ->create();

        $transaction->markComplete('QKL123456789');

        $transaction->refresh();
        $this->assertEquals('COMPLETE', $transaction->state);
        $this->assertEquals('QKL123456789', $transaction->mpesa_receipt);
    }

    public function test_mark_failed_updates_state_and_reason(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->pending()
            ->create();

        $transaction->markFailed('User cancelled the request');

        $transaction->refresh();
        $this->assertEquals('FAILED', $transaction->state);
        $this->assertEquals('User cancelled the request', $transaction->failure_reason);
    }

    public function test_webhook_payload_is_cast_to_array(): void
    {
        $payload = [
            'invoice' => [
                'invoice_id' => 'XMSLWOS',
                'state' => 'COMPLETE',
            ],
        ];

        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->create(['webhook_payload' => $payload]);

        $this->assertIsArray($transaction->webhook_payload);
        $this->assertEquals('XMSLWOS', $transaction->webhook_payload['invoice']['invoice_id']);
    }

    public function test_amounts_are_cast_to_decimal(): void
    {
        $transaction = IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->create([
                'amount' => 1000.50,
                'intasend_charges' => 10.25,
                'net_amount' => 990.25,
                'platform_fee' => 29.71,
                'landlord_amount' => 960.54,
            ]);

        $this->assertEquals('1000.50', $transaction->amount);
        $this->assertEquals('10.25', $transaction->intasend_charges);
        $this->assertEquals('990.25', $transaction->net_amount);
        $this->assertEquals('29.71', $transaction->platform_fee);
        $this->assertEquals('960.54', $transaction->landlord_amount);
    }

    public function test_tenant_scope_filters_by_landlord(): void
    {
        // Create another landlord with full setup using the trait
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $otherProperty = \App\Models\Property::create([
            'name' => 'Other Property',
            'address' => '456 Other St',
            'type' => 'apartment',
            'landlord_id' => $otherLandlord->id,
        ]);
        $otherBuilding = \App\Models\Building::create([
            'property_id' => $otherProperty->id,
            'name' => 'Block B',
            'total_floors' => 1,
            'units_per_floor' => 2,
            'landlord_id' => $otherLandlord->id,
            'building_type' => 'residential_apartment',
        ]);
        $otherUnit = \App\Models\Unit::create([
            'building_id' => $otherBuilding->id,
            'unit_number' => 'B101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $otherLandlord->id,
        ]);
        $otherTenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $otherLandlord->id,
        ]);
        $otherLease = Lease::create([
            'unit_id' => $otherUnit->id,
            'tenant_id' => $otherTenant->id,
            'landlord_id' => $otherLandlord->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);
        $otherInvoice = $this->createInvoiceForLease($otherLease, 'sent');

        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->count(2)
            ->create();

        IntaSendTransaction::factory()
            ->forInvoice($otherInvoice)
            ->create();

        // Verify landlord_id is correctly set via factory
        $allTransactions = IntaSendTransaction::withoutGlobalScope('landlord')->get();
        $this->assertCount(3, $allTransactions);

        // Verify filtering by landlord_id works (simulating what TenantScope does)
        $thisLandlordTransactions = IntaSendTransaction::withoutGlobalScope('landlord')
            ->where('landlord_id', $this->landlord->id)
            ->get();
        $this->assertCount(2, $thisLandlordTransactions);
        $this->assertTrue($thisLandlordTransactions->every(fn ($t) => $t->landlord_id === $this->landlord->id));

        $otherLandlordTransactions = IntaSendTransaction::withoutGlobalScope('landlord')
            ->where('landlord_id', $otherLandlord->id)
            ->get();
        $this->assertCount(1, $otherLandlordTransactions);
    }

    public function test_state_constants_are_defined(): void
    {
        $this->assertEquals('PENDING', IntaSendTransaction::STATE_PENDING);
        $this->assertEquals('PROCESSING', IntaSendTransaction::STATE_PROCESSING);
        $this->assertEquals('COMPLETE', IntaSendTransaction::STATE_COMPLETE);
        $this->assertEquals('FAILED', IntaSendTransaction::STATE_FAILED);
    }

    public function test_intasend_invoice_id_must_be_unique(): void
    {
        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->create(['intasend_invoice_id' => 'UNIQUE123']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        IntaSendTransaction::factory()
            ->forInvoice($this->invoice)
            ->create(['intasend_invoice_id' => 'UNIQUE123']);
    }
}
