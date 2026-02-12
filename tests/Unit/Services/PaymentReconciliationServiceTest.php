<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Services\PaystackService;
use App\Services\Reconciliation\PaymentReconciliationService;
use App\ValueObjects\ReconciliationResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentReconciliationServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected PaymentReconciliationService $service;

    protected MockInterface $mockPaystack;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupData = $this->createLandlordWithFullSetup();
        $this->mockPaystack = Mockery::mock(PaystackService::class);
        $this->mockPaystack->shouldReceive('withConfig')->andReturnSelf();
        $this->service = new PaymentReconciliationService($this->mockPaystack);
    }

    public function test_returns_empty_result_when_paystack_not_configured(): void
    {
        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertInstanceOf(ReconciliationResult::class, $result);
        $this->assertFalse($result->hasDiscrepancies());
        $this->assertEquals(0, $result->localCount);
        $this->assertEquals(0, $result->remoteCount);
        $this->assertEquals(0, $result->matchedCount);
    }

    public function test_perfect_match_returns_no_discrepancies(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $refs = ['PSK_MATCH_001', 'PSK_MATCH_002', 'PSK_MATCH_003'];
        $amounts = [15000.00, 25000.00, 10000.00];

        foreach ($refs as $i => $ref) {
            $this->createPaystackPayment($lease, $ref, $amounts[$i]);
        }

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn($this->makePaystackResponse([
                $this->makePaystackTransaction('PSK_MATCH_001', 1500000),
                $this->makePaystackTransaction('PSK_MATCH_002', 2500000),
                $this->makePaystackTransaction('PSK_MATCH_003', 1000000),
            ]));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertFalse($result->hasDiscrepancies());
        $this->assertEquals(3, $result->matchedCount);
        $this->assertEquals(3, $result->localCount);
        $this->assertEquals(3, $result->remoteCount);
    }

    public function test_detects_missing_locally(): void
    {
        $this->createPaystackConfig();

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn($this->makePaystackResponse([
                $this->makePaystackTransaction('PSK_ORPHAN_001', 5000000, 'KES'),
            ]));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertTrue($result->hasDiscrepancies());
        $this->assertCount(1, $result->missingLocally());
        $discrepancy = $result->missingLocally()[0];
        $this->assertEquals('PSK_ORPHAN_001', $discrepancy->reference);
        $this->assertEquals(50000.00, $discrepancy->remoteAmount);
        $this->assertNull($discrepancy->localAmount);
        $this->assertEquals('KES', $discrepancy->currency);
    }

    public function test_detects_missing_remotely(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $this->createPaystackPayment($lease, 'PSK_PHANTOM_001', 20000.00);

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn($this->makePaystackResponse([]));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertTrue($result->hasDiscrepancies());
        $this->assertCount(1, $result->missingRemotely());
        $discrepancy = $result->missingRemotely()[0];
        $this->assertEquals('PSK_PHANTOM_001', $discrepancy->reference);
        $this->assertEquals(20000.00, $discrepancy->localAmount);
        $this->assertNull($discrepancy->remoteAmount);
    }

    public function test_detects_amount_mismatch(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $this->createPaystackPayment($lease, 'PSK_MISMATCH_001', 15000.00);

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn($this->makePaystackResponse([
                $this->makePaystackTransaction('PSK_MISMATCH_001', 2000000),
            ]));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertTrue($result->hasDiscrepancies());
        $this->assertCount(1, $result->amountMismatches());
        $this->assertEquals(0, $result->matchedCount);

        $discrepancy = $result->amountMismatches()[0];
        $this->assertEquals('PSK_MISMATCH_001', $discrepancy->reference);
        $this->assertEquals(15000.00, $discrepancy->localAmount);
        $this->assertEquals(20000.00, $discrepancy->remoteAmount);
    }

    public function test_handles_mixed_discrepancies(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $this->createPaystackPayment($lease, 'PSK_OK_001', 10000.00);
        $this->createPaystackPayment($lease, 'PSK_MISMATCH_002', 15000.00);
        $this->createPaystackPayment($lease, 'PSK_PHANTOM_002', 8000.00);

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn($this->makePaystackResponse([
                $this->makePaystackTransaction('PSK_OK_001', 1000000),
                $this->makePaystackTransaction('PSK_MISMATCH_002', 2000000),
                $this->makePaystackTransaction('PSK_ORPHAN_002', 3000000),
            ]));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertEquals(1, $result->matchedCount);
        $this->assertCount(1, $result->missingLocally());
        $this->assertCount(1, $result->missingRemotely());
        $this->assertCount(1, $result->amountMismatches());
        $this->assertEquals(3, $result->discrepancyCount());
    }

    public function test_excludes_voided_payments(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        Payment::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 10000,
            'payment_method' => 'paystack',
            'paystack_reference' => 'PSK_VOIDED_001',
            'payment_date' => now(),
            'reference' => 'PAY-VOID-RECON-1',
            'is_voided' => true,
            'voided_at' => now(),
            'void_reason' => 'Test void',
        ]);

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn($this->makePaystackResponse([]));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertEquals(0, $result->localCount);
        $this->assertFalse($result->hasDiscrepancies());
    }

    public function test_handles_paginated_response(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $this->createPaystackPayment($lease, 'PSK_PAGE1_001', 10000.00);
        $this->createPaystackPayment($lease, 'PSK_PAGE2_001', 20000.00);

        $this->mockPaystack->shouldReceive('listTransactions')
            ->with(Mockery::on(fn ($params) => ($params['page'] ?? 1) === 1))
            ->once()
            ->andReturn($this->makePaystackResponse(
                [$this->makePaystackTransaction('PSK_PAGE1_001', 1000000)],
                page: 1,
                hasNext: true,
            ));

        $this->mockPaystack->shouldReceive('listTransactions')
            ->with(Mockery::on(fn ($params) => ($params['page'] ?? 1) === 2))
            ->once()
            ->andReturn($this->makePaystackResponse(
                [$this->makePaystackTransaction('PSK_PAGE2_001', 2000000)],
                page: 2,
                hasNext: false,
            ));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertEquals(2, $result->matchedCount);
        $this->assertEquals(2, $result->remoteCount);
        $this->assertFalse($result->hasDiscrepancies());
    }

    public function test_handles_api_failure_gracefully(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $this->createPaystackPayment($lease, 'PSK_LONELY_001', 5000.00);

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn(null);

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertInstanceOf(ReconciliationResult::class, $result);
        $this->assertEquals(0, $result->remoteCount);
        $this->assertEquals(1, $result->localCount);
        $this->assertCount(1, $result->missingRemotely());
    }

    public function test_converts_kobo_to_major_units_correctly(): void
    {
        $this->createPaystackConfig();
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $this->createPaystackPayment($lease, 'PSK_KOBO_001', 2500.00);

        $this->mockPaystack->shouldReceive('listTransactions')
            ->once()
            ->andReturn($this->makePaystackResponse([
                $this->makePaystackTransaction('PSK_KOBO_001', 250000),
            ]));

        $result = $this->service->reconcilePaystack(
            $this->setupData['landlord']->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );

        $this->assertEquals(1, $result->matchedCount);
        $this->assertFalse($result->hasDiscrepancies());
    }

    // -- Helpers --

    private function createPaystackConfig(): PaymentConfiguration
    {
        return PaymentConfiguration::factory()
            ->forLandlord($this->setupData['landlord'])
            ->withPaystack()
            ->create([
                'paystack_public_key' => 'pk_test_recon_001',
                'paystack_secret_key' => 'sk_test_recon_001',
            ]);
    }

    private function createPaystackPayment($lease, string $ref, float $amount): Payment
    {
        return Payment::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => $amount,
            'payment_method' => 'paystack',
            'paystack_reference' => $ref,
            'payment_date' => now(),
            'reference' => 'PAY-RECON-'.uniqid(),
        ]);
    }

    private function makePaystackResponse(array $transactions, int $page = 1, bool $hasNext = false): array
    {
        return [
            'status' => true,
            'data' => $transactions,
            'meta' => [
                'page' => $page,
                'perPage' => 100,
                'next' => $hasNext ? 'https://api.paystack.co/transaction?page='.($page + 1) : null,
                'previous' => $page > 1 ? 'https://api.paystack.co/transaction?page='.($page - 1) : null,
            ],
        ];
    }

    private function makePaystackTransaction(string $reference, int $amountKobo, string $currency = 'KES'): array
    {
        return [
            'reference' => $reference,
            'amount' => $amountKobo,
            'currency' => $currency,
            'status' => 'success',
            'paid_at' => now()->toIso8601String(),
        ];
    }
}
