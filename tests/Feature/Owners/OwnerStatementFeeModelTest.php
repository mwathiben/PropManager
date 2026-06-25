<?php

declare(strict_types=1);

namespace Tests\Feature\Owners;

use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Enums\ManagementFeeType;
use App\Models\PropertyOwner;
use App\Services\OwnerStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Slice-2 PR-2.3b: the owner statement must compute the management fee from the
 * FULL fee model (collected/billed/scheduled base, per-period/per-unit flat) via
 * ManagementFeeCalculator — not the collected-only shortcut. Otherwise an owner
 * who signed "X% of rent billed" or "flat per occupied unit" is mis-charged on a
 * collected basis, drifting the owner's net away from the signed agreement.
 */
class OwnerStatementFeeModelTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(): array
    {
        return [Carbon::now()->subMonth(), Carbon::now()->addDay()];
    }

    public function test_percentage_fee_on_billed_uses_invoiced_not_collected(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $unit = $setup['units']->get(0);
        $unit->update(['target_rent' => 20000]);
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $unit);
        // Invoice total_due 20000 (billed); payment 12000 (collected) — deliberately different.
        $this->createPaymentWithInvoice($lease, 12000);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create([
            'management_fee_type' => ManagementFeeType::Percentage,
            'management_fee_value' => 10,
            'management_fee_base' => ManagementFeeBase::Billed,
        ]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        [$start, $end] = $this->period();
        $data = app(OwnerStatementService::class)->forOwner($landlord->id, $owner->id, $start, $end);

        $this->assertEqualsWithDelta(12000.0, $data['collected'], 0.01);
        $this->assertEqualsWithDelta(2000.0, $data['management_fee'], 0.01); // 10% of billed 20000, not collected 12000
    }

    public function test_percentage_fee_on_scheduled_uses_contracted_rent(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $unit = $setup['units']->get(0);
        $unit->update(['target_rent' => 20000]);
        $this->createTenantWithActiveLease($landlord, $unit); // active lease rent 20000; no payments/invoices

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create([
            'management_fee_type' => ManagementFeeType::Percentage,
            'management_fee_value' => 10,
            'management_fee_base' => ManagementFeeBase::Scheduled,
        ]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        [$start, $end] = $this->period();
        $data = app(OwnerStatementService::class)->forOwner($landlord->id, $owner->id, $start, $end);

        $this->assertEqualsWithDelta(0.0, $data['collected'], 0.01);
        $this->assertEqualsWithDelta(2000.0, $data['management_fee'], 0.01); // 10% of scheduled rent 20000
    }

    public function test_flat_fee_per_unit_scales_with_occupied_units(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $units = $setup['units'];
        // Two occupied units under this owner's property.
        $this->createTenantWithActiveLease($landlord, $units->get(0));
        $this->createTenantWithActiveLease($landlord, $units->get(1));

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create([
            'management_fee_type' => ManagementFeeType::Flat,
            'management_fee_value' => 1000,
            'management_fee_flat_cadence' => ManagementFeeFlatCadence::PerUnit,
        ]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        [$start, $end] = $this->period();
        $data = app(OwnerStatementService::class)->forOwner($landlord->id, $owner->id, $start, $end);

        $this->assertEqualsWithDelta(2000.0, $data['management_fee'], 0.01); // 1000 * 2 occupied units
    }

    public function test_percentage_fee_on_collected_is_unchanged(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $unit = $setup['units']->get(0);
        $unit->update(['target_rent' => 20000]);
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $unit);
        $this->createPaymentWithInvoice($lease, 12000);

        $owner = PropertyOwner::factory()->forLandlord($landlord)->create([
            'management_fee_type' => ManagementFeeType::Percentage,
            'management_fee_value' => 10,
            'management_fee_base' => ManagementFeeBase::Collected,
        ]);
        $setup['property']->update(['property_owner_id' => $owner->id]);

        [$start, $end] = $this->period();
        $data = app(OwnerStatementService::class)->forOwner($landlord->id, $owner->id, $start, $end);

        $this->assertEqualsWithDelta(1200.0, $data['management_fee'], 0.01); // 10% of collected 12000
    }
}
