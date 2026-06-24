<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\ClauseBinding;
use App\Exceptions\DataIntegrityException;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use Database\Seeders\ManagementClauseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Slice-2 PR-2.1: the clause/agreement domain — "the contract is the config".
 * A management agreement is composed of clause instances (each carrying chosen
 * params); the fee clause is the one bound to PropertyOwner.management_fee_*
 * (wired + locked in PR 2.3). This PR only models + renders + seeds it.
 */
class ManagementAgreementDomainTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function ownerFor(User $manager): PropertyOwner
    {
        return PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
    }

    public function test_an_agreement_composes_clauses_and_renders_a_hashed_body(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $owner = $this->ownerFor($manager);

        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Draft,
        ]);

        $feeClause = Clause::factory()->managementFee()->create();
        $noticeClause = Clause::factory()->create([
            'binding' => ClauseBinding::Notice,
            'body_template' => 'Either party may end this agreement on {notice_days} days notice.',
        ]);

        $agreement->agreementClauses()->create([
            'clause_id' => $feeClause->id,
            'params' => ['type' => 'percentage', 'value' => 8, 'base' => 'collected'],
            'position' => 0,
        ]);
        $agreement->agreementClauses()->create([
            'clause_id' => $noticeClause->id,
            'params' => ['notice_days' => 30],
            'position' => 1,
        ]);

        $agreement->recomputeRenderedBody();

        $this->assertStringContainsString('8% of rent collected', $agreement->rendered_body);
        $this->assertStringContainsString('30 days notice', $agreement->rendered_body);
        $this->assertSame(hash('sha256', $agreement->rendered_body), $agreement->content_hash);
    }

    public function test_an_exclusive_clause_cannot_be_added_twice(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $this->ownerFor($manager)->id,
        ]);

        $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->managementFee()->create()->id,
            'params' => ['type' => 'percentage', 'value' => 8, 'base' => 'collected'],
        ]);

        $this->expectException(DataIntegrityException::class);
        $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->managementFee()->create()->id,
            'params' => ['type' => 'flat', 'value' => 5000],
        ]);
    }

    public function test_a_signed_snapshot_cannot_be_re_rendered(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $this->ownerFor($manager)->id,
            'status' => AgreementStatus::Signed,
        ]);

        $this->expectException(DataIntegrityException::class);
        $agreement->recomputeRenderedBody();
    }

    public function test_fee_clause_is_identifiable_for_the_applicator(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $owner = $this->ownerFor($manager);

        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
        ]);
        $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->create(['binding' => ClauseBinding::Notice])->id,
            'params' => ['notice_days' => 30],
        ]);
        $feeInstance = $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->managementFee()->create()->id,
            'params' => ['type' => 'percentage', 'value' => 8, 'base' => 'collected'],
        ]);

        $found = $agreement->feeClause();

        $this->assertNotNull($found);
        $this->assertSame($feeInstance->id, $found->id);
        $this->assertTrue($found->clause->binding->governsConfig());
    }

    public function test_agreement_is_scoped_to_the_managing_account(): void
    {
        $managerA = $this->manager();
        $this->actingAs($managerA);
        $agreementA = ManagementAgreement::factory()->create([
            'landlord_id' => $managerA->id,
            'property_owner_id' => $this->ownerFor($managerA)->id,
        ]);

        $managerB = $this->manager();
        $this->actingAs($managerB);

        $this->assertNull(ManagementAgreement::find($agreementA->id), 'TenantScope must hide another account\'s agreement.');
    }

    public function test_clause_seeder_is_idempotent_and_flags_legal_review(): void
    {
        (new ManagementClauseSeeder)->run();
        $afterFirst = Clause::count();
        (new ManagementClauseSeeder)->run();

        $this->assertSame($afterFirst, Clause::count(), 'Re-running the seeder must not duplicate clauses.');
        $this->assertGreaterThanOrEqual(6, $afterFirst);
        $this->assertTrue(
            Clause::where('binding', ClauseBinding::ManagementFee)->exists(),
            'A management-fee clause must be seeded.',
        );
        $this->assertSame(
            0,
            Clause::where('needs_legal_review', false)->count(),
            'Every seeded clause is DRAFT pending advocate review.',
        );
    }
}
