<?php

namespace Tests\Feature\Services;

use App\Enums\InvoiceStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\Payment\BulkPaymentProcessor;
use App\Services\Payment\BulkPaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class BulkPaymentProcessorTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected BulkPaymentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
        Mail::fake();

        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_xxxxx',
            'paystack_secret_key' => 'sk_test_xxxxx',
        ]);

        $this->processor = app(BulkPaymentProcessor::class);
    }

    // ── Current Mode Tests ──────────────────────────────────────────────

    public function test_processes_single_payment_with_invoice_allocation(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $result = $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => [
                [
                    'tenant_id' => $tenant->id,
                    'amount' => (float) $invoice->total_due,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => (float) $invoice->total_due,
                            'outstanding_before' => (float) $invoice->total_due,
                        ],
                    ],
                    'wallet_credit' => 0,
                ],
            ],
        ]);

        $this->assertInstanceOf(BulkPaymentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->successCount);
        $this->assertEquals(0, $result->failedCount);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => $invoice->total_due,
            'payment_method' => 'cash',
            'notes' => 'Bulk import',
        ]);
    }

    public function test_processes_multiple_payments_in_batch(): void
    {
        $units = $this->setupData['units']->take(3);
        $payments = [];

        foreach ($units as $unit) {
            ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
            $invoice = $this->createInvoiceForLease($lease, 'sent');

            $payments[] = [
                'tenant_id' => $tenant->id,
                'amount' => (float) $invoice->total_due,
                'payment_method' => 'mpesa',
                'payment_date' => now()->toDateString(),
                'reference' => null,
                'allocations' => [
                    [
                        'invoice_id' => $invoice->id,
                        'amount' => (float) $invoice->total_due,
                        'outstanding_before' => (float) $invoice->total_due,
                    ],
                ],
                'wallet_credit' => 0,
            ];
        }

        $result = $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => $payments,
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(3, $result->successCount);
        $this->assertEquals(0, $result->failedCount);
    }

    public function test_updates_invoice_to_paid_when_fully_paid(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => [
                [
                    'tenant_id' => $tenant->id,
                    'amount' => (float) $invoice->total_due,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => (float) $invoice->total_due,
                            'outstanding_before' => (float) $invoice->total_due,
                        ],
                    ],
                    'wallet_credit' => 0,
                ],
            ],
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_updates_invoice_to_partial_when_partially_paid(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $partialAmount = (float) $invoice->total_due / 2;

        $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => [
                [
                    'tenant_id' => $tenant->id,
                    'amount' => $partialAmount,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => $partialAmount,
                            'outstanding_before' => (float) $invoice->total_due,
                        ],
                    ],
                    'wallet_credit' => 0,
                ],
            ],
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Partial, $invoice->status);
        $this->assertEquals($partialAmount, (float) $invoice->amount_paid);
    }

    public function test_creates_receipt_for_each_allocation(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => [
                [
                    'tenant_id' => $tenant->id,
                    'amount' => (float) $invoice->total_due,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => (float) $invoice->total_due,
                            'outstanding_before' => (float) $invoice->total_due,
                        ],
                    ],
                    'wallet_credit' => 0,
                ],
            ],
        ]);

        $this->assertDatabaseHas('receipts', [
            'invoice_id' => $invoice->id,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_credits_wallet_for_overpayment(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $walletCredit = 5000.0;

        $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => [
                [
                    'tenant_id' => $tenant->id,
                    'amount' => (float) $invoice->total_due + $walletCredit,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => (float) $invoice->total_due,
                            'outstanding_before' => (float) $invoice->total_due,
                        ],
                    ],
                    'wallet_credit' => $walletCredit,
                ],
            ],
        ]);

        $lease->refresh();
        $this->assertEquals($walletCredit, (float) $lease->wallet_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'lease_id' => $lease->id,
            'amount' => $walletCredit,
        ]);
    }

    public function test_partial_success_on_invalid_invoice(): void
    {
        $unit1 = $this->setupData['units'][0];
        $unit2 = $this->setupData['units'][1];

        ['tenant' => $tenant1, 'lease' => $lease1] = $this->createTenantWithActiveLease($this->landlord, $unit1);
        $invoice1 = $this->createInvoiceForLease($lease1, 'sent');

        ['tenant' => $tenant2] = $this->createTenantWithActiveLease($this->landlord, $unit2);

        $result = $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => [
                [
                    'tenant_id' => $tenant1->id,
                    'amount' => (float) $invoice1->total_due,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => $invoice1->id,
                            'amount' => (float) $invoice1->total_due,
                            'outstanding_before' => (float) $invoice1->total_due,
                        ],
                    ],
                    'wallet_credit' => 0,
                ],
                [
                    'tenant_id' => $tenant2->id,
                    'amount' => 15000,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => 999999,
                            'amount' => 15000,
                            'outstanding_before' => 15000,
                        ],
                    ],
                    'wallet_credit' => 0,
                ],
            ],
        ]);

        $this->assertEquals(1, $result->successCount);
        $this->assertEquals(1, $result->failedCount);
        $this->assertNotEmpty($result->errors);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice1->id,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_returns_correct_total_amount(): void
    {
        $units = $this->setupData['units']->take(2);
        $payments = [];
        $expectedTotal = 0;

        foreach ($units as $unit) {
            ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
            $invoice = $this->createInvoiceForLease($lease, 'sent');
            $expectedTotal += (float) $invoice->total_due;

            $payments[] = [
                'tenant_id' => $tenant->id,
                'amount' => (float) $invoice->total_due,
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
                'reference' => null,
                'allocations' => [
                    [
                        'invoice_id' => $invoice->id,
                        'amount' => (float) $invoice->total_due,
                        'outstanding_before' => (float) $invoice->total_due,
                    ],
                ],
                'wallet_credit' => 0,
            ];
        }

        $result = $this->processor->process($this->landlord->id, [
            'mode' => 'current',
            'payments' => $payments,
        ]);

        $this->assertEquals($expectedTotal, $result->totalAmount);
    }

    // ── Historical Mode Tests ───────────────────────────────────────────

    public function test_creates_archived_tenant_when_not_exists(): void
    {
        $result = $this->processor->process($this->landlord->id, [
            'mode' => 'historical',
            'building_id' => $this->setupData['building']->id,
            'payments' => [
                [
                    'unit_id' => $this->setupData['units']->first()->id,
                    'tenant_name' => 'Former Tenant',
                    'tenant_email' => 'former@example.com',
                    'amount' => 12000,
                    'payment_method' => 'cash',
                    'payment_date' => '2023-06-15',
                    'reference' => null,
                ],
            ],
        ]);

        $this->assertInstanceOf(BulkPaymentResult::class, $result);

        $this->assertDatabaseHas('users', [
            'name' => 'Former Tenant',
            'email' => 'former@example.com',
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'is_archived' => true,
        ]);
    }

    public function test_reuses_existing_archived_tenant_case_insensitive(): void
    {
        $existingTenant = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@archived.local',
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $this->processor->process($this->landlord->id, [
            'mode' => 'historical',
            'building_id' => $this->setupData['building']->id,
            'payments' => [
                [
                    'unit_id' => $this->setupData['units']->first()->id,
                    'tenant_name' => 'john doe',
                    'tenant_email' => null,
                    'amount' => 12000,
                    'payment_method' => 'cash',
                    'payment_date' => '2023-06-15',
                    'reference' => null,
                ],
            ],
        ]);

        $archivedTenants = User::where('landlord_id', $this->landlord->id)
            ->where('role', 'tenant')
            ->where('is_archived', true)
            ->count();

        $this->assertEquals(1, $archivedTenants);
    }

    public function test_creates_historical_lease_with_payment_date_range(): void
    {
        $unit = $this->setupData['units']->first();

        User::factory()->create([
            'name' => 'Test Tenant',
            'email' => 'test-historical@example.com',
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $this->processor->process($this->landlord->id, [
            'mode' => 'historical',
            'building_id' => $this->setupData['building']->id,
            'payments' => [
                [
                    'unit_id' => $unit->id,
                    'tenant_name' => 'Test Tenant',
                    'tenant_email' => null,
                    'amount' => 12000,
                    'payment_method' => 'cash',
                    'payment_date' => '2023-06-15',
                    'reference' => null,
                ],
            ],
        ]);

        $lease = Lease::where('landlord_id', $this->landlord->id)
            ->where('unit_id', $unit->id)
            ->where('is_active', false)
            ->first();

        $this->assertNotNull($lease);
        $this->assertEquals('2023-06-15', $lease->start_date->toDateString());
        $this->assertEquals('2023-06-15', $lease->end_date->toDateString());
        $this->assertEquals(0, (float) $lease->rent_amount);
    }

    public function test_expands_historical_lease_date_range(): void
    {
        $unit = $this->setupData['units']->first();

        $archivedTenant = User::factory()->create([
            'name' => 'Range Tenant',
            'email' => 'range@archived.local',
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $archivedTenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2023-06-15',
            'end_date' => '2023-06-15',
            'rent_amount' => 0,
            'deposit_amount' => 0,
            'is_active' => false,
        ]);

        $this->processor->process($this->landlord->id, [
            'mode' => 'historical',
            'building_id' => $this->setupData['building']->id,
            'payments' => [
                [
                    'unit_id' => $unit->id,
                    'tenant_name' => 'Range Tenant',
                    'tenant_email' => null,
                    'amount' => 12000,
                    'payment_method' => 'cash',
                    'payment_date' => '2023-09-15',
                    'reference' => null,
                ],
            ],
        ]);

        $lease = Lease::where('landlord_id', $this->landlord->id)
            ->where('unit_id', $unit->id)
            ->where('tenant_id', $archivedTenant->id)
            ->where('is_active', false)
            ->first();

        $this->assertEquals('2023-06-15', $lease->start_date->toDateString());
        $this->assertEquals('2023-09-15', $lease->end_date->toDateString());
    }

    public function test_historical_payment_created_with_correct_data(): void
    {
        $unit = $this->setupData['units']->first();

        $this->processor->process($this->landlord->id, [
            'mode' => 'historical',
            'building_id' => $this->setupData['building']->id,
            'payments' => [
                [
                    'unit_id' => $unit->id,
                    'tenant_name' => 'History Tenant',
                    'tenant_email' => 'history@example.com',
                    'amount' => 15000,
                    'payment_method' => 'mpesa',
                    'payment_date' => '2023-03-01',
                    'reference' => 'HIST-REF-001',
                ],
            ],
        ]);

        $archivedTenant = User::where('email', 'history@example.com')->first();
        $this->assertNotNull($archivedTenant);

        $lease = Lease::where('tenant_id', $archivedTenant->id)
            ->where('unit_id', $unit->id)
            ->first();
        $this->assertNotNull($lease);
        $this->assertFalse((bool) $lease->is_active);

        $payment = Payment::where('lease_id', $lease->id)->first();
        $this->assertNotNull($payment, 'Payment record should exist for archived tenant');
        $this->assertEquals(15000, $payment->amount);
        $this->assertEquals('mpesa', $payment->payment_method);
        $this->assertEquals('HIST-REF-001', $payment->reference);
        $this->assertEquals('2023-03-01', $payment->payment_date->toDateString());
    }

    public function test_historical_partial_success_on_error(): void
    {
        $unit = $this->setupData['units']->first();

        $result = $this->processor->process($this->landlord->id, [
            'mode' => 'historical',
            'building_id' => $this->setupData['building']->id,
            'payments' => [
                [
                    'unit_id' => $unit->id,
                    'tenant_name' => 'Good Tenant',
                    'tenant_email' => 'good@example.com',
                    'amount' => 12000,
                    'payment_method' => 'cash',
                    'payment_date' => '2023-06-15',
                    'reference' => null,
                ],
                [
                    'unit_id' => $unit->id,
                    'tenant_name' => 'Bad Tenant',
                    'tenant_email' => 'bad@example.com',
                    'amount' => -500,
                    'payment_method' => 'cash',
                    'payment_date' => '2023-06-15',
                    'reference' => null,
                ],
            ],
        ]);

        $this->assertInstanceOf(BulkPaymentResult::class, $result);
        $this->assertNotEmpty($result->errors);
    }

    // ── Meta Tests ──────────────────────────────────────────────────────

    public function test_defaults_to_current_mode_when_mode_omitted(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $result = $this->processor->process($this->landlord->id, [
            'payments' => [
                [
                    'tenant_id' => $tenant->id,
                    'amount' => (float) $invoice->total_due,
                    'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'allocations' => [
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => (float) $invoice->total_due,
                            'outstanding_before' => (float) $invoice->total_due,
                        ],
                    ],
                    'wallet_credit' => 0,
                ],
            ],
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->successCount);
    }

    public function test_result_to_array_format(): void
    {
        $result = BulkPaymentResult::succeeded(5, 125000.0);
        $array = $result->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('success_count', $array);
        $this->assertArrayHasKey('failed_count', $array);
        $this->assertArrayHasKey('total_amount', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayNotHasKey('archived_tenants_created', $array);

        $resultWithArchived = BulkPaymentResult::succeeded(3, 36000.0, 2);
        $arrayWithArchived = $resultWithArchived->toArray();
        $this->assertArrayHasKey('archived_tenants_created', $arrayWithArchived);
        $this->assertEquals(2, $arrayWithArchived['archived_tenants_created']);
    }
}
