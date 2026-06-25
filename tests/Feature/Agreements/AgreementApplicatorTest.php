<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Enums\ManagementFeeType;
use App\Exceptions\DataIntegrityException;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Services\Agreements\AgreementApplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice-2 PR-2.3: activating a signed agreement is where "the contract is the
 * config" becomes real — AgreementApplicator writes the fee clause's governed
 * values onto PropertyOwner.management_fee_* and LOCKS them (immutable except
 * via a re-signed amendment). This is the money-correctness seam, so it is
 * exercised directly.
 */
class AgreementApplicatorTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function signedAgreementWithFeeClause(User $manager, PropertyOwner $owner, array $params): ManagementAgreement
    {
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Signed,
        ]);
        $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->managementFee()->create()->id,
            'params' => $params,
            'position' => 0,
        ]);

        return $agreement;
    }

    public function test_activating_writes_and_locks_a_percentage_fee(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id, 'management_fee_type' => ManagementFeeType::None]);
        $agreement = $this->signedAgreementWithFeeClause($manager, $owner, ['type' => 'percentage', 'value' => 8, 'base' => 'collected']);

        app(AgreementApplicator::class)->activate($agreement);

        $owner->refresh();
        $this->assertSame(ManagementFeeType::Percentage, $owner->management_fee_type);
        $this->assertEquals(8.0, (float) $owner->management_fee_value);
        $this->assertSame(ManagementFeeBase::Collected, $owner->management_fee_base);
        $this->assertNotNull($owner->management_fee_locked_at, 'fee must be locked after activation');
        $this->assertSame($agreement->id, $owner->management_agreement_id);

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertNotNull($agreement->fresh()->activated_at);
    }

    public function test_activating_writes_a_flat_fee(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
        $agreement = $this->signedAgreementWithFeeClause($manager, $owner, ['type' => 'flat', 'value' => 5000, 'flat_cadence' => 'per_unit']);

        app(AgreementApplicator::class)->activate($agreement);

        $owner->refresh();
        $this->assertSame(ManagementFeeType::Flat, $owner->management_fee_type);
        $this->assertEquals(5000.0, (float) $owner->management_fee_value);
        $this->assertSame(ManagementFeeFlatCadence::PerUnit, $owner->management_fee_flat_cadence);
    }

    public function test_a_locked_fee_refuses_a_direct_edit(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
        $agreement = $this->signedAgreementWithFeeClause($manager, $owner, ['type' => 'percentage', 'value' => 8, 'base' => 'collected']);
        app(AgreementApplicator::class)->activate($agreement);

        $this->expectException(DataIntegrityException::class);
        $owner->fresh()->update(['management_fee_value' => 99]);
    }

    public function test_activate_refuses_an_unsigned_agreement(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Draft,
        ]);

        $this->expectException(DataIntegrityException::class);
        app(AgreementApplicator::class)->activate($agreement);
    }
}
