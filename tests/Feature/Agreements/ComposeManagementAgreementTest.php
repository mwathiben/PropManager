<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementStatus;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use Database\Seeders\ManagementClauseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Slice-2 PR-2.2: a manager composes a DRAFT management agreement from clauses.
 * No owner invite / e-sign / fee-apply yet (PR 2.3) — this only creates + renders
 * the draft, manager-scoped and validated.
 */
class ComposeManagementAgreementTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    /** @return array<string, mixed> */
    private function payloadFor(PropertyOwner $owner): array
    {
        return [
            'title' => 'Management agreement',
            'property_owner_id' => $owner->id,
            'clauses' => [
                ['clause_id' => Clause::factory()->managementFee()->create()->id, 'params' => ['type' => 'percentage', 'value' => 8, 'base' => 'collected']],
                ['clause_id' => Clause::factory()->create(['binding' => \App\Enums\ClauseBinding::Notice, 'body_template' => 'End on {notice_days} days notice.'])->id, 'params' => ['notice_days' => 30]],
            ],
        ];
    }

    public function test_manager_composes_a_draft_agreement(): void
    {
        $manager = $this->manager();
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);

        $response = $this->actingAs($manager)->post(route('agreements.store'), $this->payloadFor($owner));

        $agreement = ManagementAgreement::where('property_owner_id', $owner->id)->first();
        $this->assertNotNull($agreement);
        $response->assertRedirect(route('agreements.show', $agreement));
        $this->assertSame($manager->id, $agreement->landlord_id);
        $this->assertSame(AgreementStatus::Draft, $agreement->status);
        $this->assertSame(2, $agreement->agreementClauses()->count());
        $this->assertStringContainsString('8% of rent collected', $agreement->rendered_body);
        $this->assertNotNull($agreement->content_hash);
    }

    public function test_compose_rejects_an_owner_belonging_to_another_manager(): void
    {
        $managerA = $this->manager();
        $foreignOwner = PropertyOwner::factory()->create(['landlord_id' => $this->manager()->id]);

        $this->actingAs($managerA)
            ->post(route('agreements.store'), $this->payloadFor($foreignOwner))
            ->assertSessionHasErrors('property_owner_id');

        $this->assertDatabaseMissing('management_agreements', ['property_owner_id' => $foreignOwner->id]);
    }

    public function test_a_landlord_cannot_reach_the_composer(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)->get(route('agreements.create'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_from_the_composer(): void
    {
        $this->get(route('agreements.create'))->assertRedirect(route('login'));
    }

    public function test_compose_requires_owner_and_at_least_one_clause(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('agreements.store'), ['title' => 'x', 'clauses' => []])
            ->assertSessionHasErrors(['property_owner_id', 'clauses']);
    }

    public function test_create_screen_lists_owners_and_clause_catalog(): void
    {
        $manager = $this->manager();
        PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
        (new ManagementClauseSeeder)->run();

        $this->actingAs($manager)
            ->get(route('agreements.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Agreements/Compose')
                ->has('owners', 1)
                ->has('clauses'));
    }

    public function test_compose_rejects_a_fee_percentage_over_100(): void
    {
        $manager = $this->manager();
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('agreements.store'), [
                'property_owner_id' => $owner->id,
                'clauses' => [
                    ['clause_id' => Clause::factory()->managementFee()->create()->id, 'params' => ['type' => 'percentage', 'value' => 150, 'base' => 'collected']],
                ],
            ])
            ->assertSessionHasErrors('clauses.0.params');

        $this->assertDatabaseMissing('management_agreements', ['property_owner_id' => $owner->id]);
    }

    public function test_compose_rejects_a_fee_with_no_type(): void
    {
        $manager = $this->manager();
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('agreements.store'), [
                'property_owner_id' => $owner->id,
                'clauses' => [
                    ['clause_id' => Clause::factory()->managementFee()->create()->id, 'params' => ['value' => 8]],
                ],
            ])
            ->assertSessionHasErrors('clauses.0.params');

        $this->assertDatabaseMissing('management_agreements', ['property_owner_id' => $owner->id]);
    }

    public function test_compose_rejects_a_clause_with_a_blank_required_param(): void
    {
        $manager = $this->manager();
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
        $clause = Clause::factory()->create([
            'binding' => \App\Enums\ClauseBinding::Notice,
            'params_schema' => [['name' => 'notice_days']],
            'body_template' => 'Either party may end this agreement on {notice_days} days notice.',
        ]);

        $this->actingAs($manager)
            ->post(route('agreements.store'), [
                'property_owner_id' => $owner->id,
                'clauses' => [
                    ['clause_id' => $clause->id, 'params' => []],
                ],
            ])
            ->assertSessionHasErrors('clauses.0.params.notice_days');

        $this->assertDatabaseMissing('management_agreements', ['property_owner_id' => $owner->id]);
    }
}
