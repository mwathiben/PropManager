<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\LegalHold;
use App\Models\LegalMatter;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-72 WIZARD-FLOW: the guided create-hold submission creates a matter +
 * holds across types in one transaction, rolls back on a cross-tenant subject,
 * stores the situation, and requires at least one subject.
 */
class Phase72WizardTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private User $otherLandlord;

    private User $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->tenant = $this->createTenantWithActiveLease($this->landlord, $setup['units']->first())['tenant'];

        $otherSetup = $this->createLandlordWithFullSetup();
        $this->otherLandlord = $otherSetup['landlord'];
        $this->otherTenant = $this->createTenantWithActiveLease($this->otherLandlord, $otherSetup['units']->first())['tenant'];
    }

    private function threadFor(User $landlord, User $tenant): MessageThread
    {
        $this->actingAs($landlord);
        $thread = MessageThread::create(['landlord_id' => $landlord->id]);
        $thread->participants()->attach($tenant->id, ['role' => MessageThread::ROLE_TENANT]);

        return $thread;
    }

    public function test_create_renders_the_wizard(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('legal-holds.wizard'))
            ->assertInertia(fn ($page) => $page
                ->component('LegalHolds/Wizard')
                ->has('tenants')
                ->has('situations'),
            );
    }

    public function test_store_creates_a_matter_and_holds_in_one_transaction(): void
    {
        $thread = $this->threadFor($this->landlord, $this->tenant);

        $this->actingAs($this->landlord)
            ->post(route('legal-holds.wizard.store'), [
                'title' => 'Smith v. Property Co.',
                'matter_reference' => 'CV/2026/0123',
                'situation' => 'litigation',
                'reason' => 'Preserved for active litigation.',
                'subjects' => [MessageThread::class => [$thread->id]],
            ])
            ->assertRedirect();

        $matter = LegalMatter::where('landlord_id', $this->landlord->id)->first();
        $this->assertNotNull($matter);
        $this->assertSame('Smith v. Property Co.', $matter->title);
        $this->assertSame('litigation', $matter->situation_type);

        $hold = LegalHold::where('holdable_type', MessageThread::class)->where('holdable_id', $thread->id)->first();
        $this->assertNotNull($hold);
        $this->assertSame($matter->id, $hold->legal_matter_id);
    }

    public function test_store_rolls_back_on_a_cross_tenant_subject(): void
    {
        $foreignThread = $this->threadFor($this->otherLandlord, $this->otherTenant);

        $this->actingAs($this->landlord)
            ->post(route('legal-holds.wizard.store'), [
                'title' => 'Sneaky',
                'reason' => 'Trying to hold a foreign record.',
                'subjects' => [MessageThread::class => [$foreignThread->id]],
            ])
            ->assertSessionHasErrors('subjects');

        // Atomic: the matter created earlier in the transaction is rolled back.
        $this->assertSame(0, LegalMatter::withoutGlobalScopes()->where('landlord_id', $this->landlord->id)->count());
        $this->assertSame(0, LegalHold::where('holdable_id', $foreignThread->id)->count());
    }

    public function test_store_requires_at_least_one_subject(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('legal-holds.wizard.store'), [
                'title' => 'Empty',
                'reason' => 'No subjects selected here.',
                'subjects' => [MessageThread::class => []],
            ])
            ->assertSessionHasErrors('subjects');

        $this->assertSame(0, LegalMatter::withoutGlobalScopes()->where('landlord_id', $this->landlord->id)->count());
    }

    public function test_store_rejects_an_unsupported_subject_type(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('legal-holds.wizard.store'), [
                'title' => 'Bad type',
                'reason' => 'Arbitrary class injection attempt.',
                'subjects' => ['App\\Models\\User' => [$this->tenant->id]],
            ])
            ->assertSessionHasErrors('subjects');
    }
}
