<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Payment;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformFeeTier;
use App\Models\User;
use App\Services\FeeCalculation\TransactionFeeStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TransactionFeeStrategyTieredTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private PlatformBillingSetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = PlatformBillingSetting::factory()->create([
            'transaction_fee_percentage' => 2.50,
            'minimum_fee' => 50.00,
            'maximum_fee' => null,
        ]);
    }

    public function test_uses_flat_rate_when_no_tiers_exist(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $strategy = new TransactionFeeStrategy($this->settings);

        $result = $strategy->calculateFee(10000, $landlord);

        $this->assertEquals(2.50, $result->percentageApplied);
        $this->assertEquals(250.00, $result->feeAmount);
    }

    public function test_uses_tier_rate_for_zero_mtd_landlord(): void
    {
        $this->seedDefaultTiers();
        $landlord = User::factory()->create(['role' => 'landlord']);
        $strategy = new TransactionFeeStrategy($this->settings);

        $result = $strategy->calculateFee(10000, $landlord);

        $this->assertEquals(3.00, $result->percentageApplied);
        $this->assertEquals(300.00, $result->feeAmount);
        $this->assertEquals('tiered', $result->breakdown['rate_source']);
        $this->assertEquals('Starter', $result->breakdown['tier_name']);
    }

    public function test_uses_tier_2_for_landlord_with_60k_volume(): void
    {
        $this->seedDefaultTiers();
        $setupData = $this->createLandlordWithFullSetup();
        $landlord = $setupData['landlord'];
        $unit = $setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $unit);

        $this->createPaymentsForMtd($lease, $landlord, 60000);

        $strategy = new TransactionFeeStrategy($this->settings);
        $result = $strategy->calculateFee(10000, $landlord);

        $this->assertEquals(2.50, $result->percentageApplied);
        $this->assertEquals('Growth', $result->breakdown['tier_name']);
    }

    public function test_uses_tier_3_for_landlord_with_250k_volume(): void
    {
        $this->seedDefaultTiers();
        $setupData = $this->createLandlordWithFullSetup();
        $landlord = $setupData['landlord'];
        $unit = $setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $unit);

        $this->createPaymentsForMtd($lease, $landlord, 250000);

        $strategy = new TransactionFeeStrategy($this->settings);
        $result = $strategy->calculateFee(10000, $landlord);

        $this->assertEquals(2.00, $result->percentageApplied);
        $this->assertEquals('Scale', $result->breakdown['tier_name']);
    }

    public function test_uses_tier_4_for_landlord_with_600k_volume(): void
    {
        $this->seedDefaultTiers();
        $setupData = $this->createLandlordWithFullSetup();
        $landlord = $setupData['landlord'];
        $unit = $setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $unit);

        $this->createPaymentsForMtd($lease, $landlord, 600000);

        $strategy = new TransactionFeeStrategy($this->settings);
        $result = $strategy->calculateFee(10000, $landlord);

        $this->assertEquals(1.50, $result->percentageApplied);
        $this->assertEquals('Enterprise', $result->breakdown['tier_name']);
    }

    public function test_minimum_fee_applies_with_tiered_rate(): void
    {
        $this->seedDefaultTiers();
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->settings->update(['minimum_fee' => 100.00]);
        $strategy = new TransactionFeeStrategy($this->settings);

        // 3% of 1000 = 30, but minimum is 100
        $result = $strategy->calculateFee(1000, $landlord);

        $this->assertEquals(100.00, $result->feeAmount);
        $this->assertTrue($result->breakdown['minimum_applied']);
    }

    public function test_maximum_fee_applies_with_tiered_rate(): void
    {
        $this->seedDefaultTiers();
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->settings->update(['maximum_fee' => 200.00]);
        $strategy = new TransactionFeeStrategy($this->settings);

        // 3% of 100000 = 3000, but maximum is 200
        $result = $strategy->calculateFee(100000, $landlord);

        $this->assertEquals(200.00, $result->feeAmount);
        $this->assertTrue($result->breakdown['maximum_applied']);
    }

    public function test_other_landlord_payments_dont_affect_mtd(): void
    {
        $this->seedDefaultTiers();

        $setup1 = $this->createLandlordWithFullSetup();
        $landlord1 = $setup1['landlord'];
        $unit1 = $setup1['units']->first();
        ['lease' => $lease1] = $this->createTenantWithActiveLease($landlord1, $unit1);
        $this->createPaymentsForMtd($lease1, $landlord1, 600000);

        $landlord2 = User::factory()->create(['role' => 'landlord']);
        $strategy = new TransactionFeeStrategy($this->settings);

        // landlord2 has zero volume, should get Starter tier (3%)
        $result = $strategy->calculateFee(10000, $landlord2);

        $this->assertEquals(3.00, $result->percentageApplied);
        $this->assertEquals('Starter', $result->breakdown['tier_name']);
    }

    public function test_previous_month_payments_excluded(): void
    {
        $this->seedDefaultTiers();
        $setupData = $this->createLandlordWithFullSetup();
        $landlord = $setupData['landlord'];
        $unit = $setupData['units']->first();
        $invoice = $this->createInvoiceForLease(
            $this->createTenantWithActiveLease($landlord, $unit)['lease']
        );

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $invoice->lease_id,
            'landlord_id' => $landlord->id,
            'amount' => 600000,
            'payment_method' => 'paystack',
            'reference' => 'PAY-PREV-'.uniqid(),
            'payment_date' => now()->subMonth(),
        ]);

        $strategy = new TransactionFeeStrategy($this->settings);
        $result = $strategy->calculateFee(10000, $landlord);

        // Previous month payment shouldn't count — zero MTD → Starter (3%)
        $this->assertEquals(3.00, $result->percentageApplied);
        $this->assertEquals('Starter', $result->breakdown['tier_name']);
    }

    private function seedDefaultTiers(): void
    {
        PlatformFeeTier::create(['name' => 'Starter', 'min_volume' => 0, 'max_volume' => 50000, 'fee_percentage' => 3.00, 'sort_order' => 0, 'is_active' => true]);
        PlatformFeeTier::create(['name' => 'Growth', 'min_volume' => 50000, 'max_volume' => 200000, 'fee_percentage' => 2.50, 'sort_order' => 1, 'is_active' => true]);
        PlatformFeeTier::create(['name' => 'Scale', 'min_volume' => 200000, 'max_volume' => 500000, 'fee_percentage' => 2.00, 'sort_order' => 2, 'is_active' => true]);
        PlatformFeeTier::create(['name' => 'Enterprise', 'min_volume' => 500000, 'max_volume' => null, 'fee_percentage' => 1.50, 'sort_order' => 3, 'is_active' => true]);
    }

    private function createPaymentsForMtd($lease, User $landlord, float $totalAmount): void
    {
        $invoice = $this->createInvoiceForLease($lease);

        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => $totalAmount,
            'payment_method' => 'paystack',
            'reference' => 'PAY-MTD-'.uniqid(),
            'payment_date' => now(),
        ]);
    }
}
