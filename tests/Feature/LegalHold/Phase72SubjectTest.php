<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-72 SUBJECT-PICKER: the tenant subject-suggestion endpoint — landlord-
 * owns-tenant gated, already-held flagged, cross-tenant rejected.
 */
class Phase72SubjectTest extends TestCase
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
        $thread = MessageThread::create(['landlord_id' => $landlord->id]);
        $thread->participants()->attach($tenant->id, ['role' => MessageThread::ROLE_TENANT]);

        return $thread;
    }

    public function test_suggest_returns_thread_group_for_the_tenant(): void
    {
        $this->threadFor($this->landlord, $this->tenant);

        $this->actingAs($this->landlord)
            ->getJson(route('legal-holds.subjects.suggest', ['tenant_id' => $this->tenant->id]))
            ->assertOk()
            ->assertJsonPath('tenant.id', $this->tenant->id)
            ->assertJsonPath('groups.3.type', MessageThread::class)
            ->assertJsonPath('groups.3.count', 1)
            ->assertJsonPath('groups.3.held', 0)
            ->assertJsonPath('groups.3.items.0.already_held', false);
    }

    public function test_suggest_flags_already_held_subjects(): void
    {
        $thread = $this->threadFor($this->landlord, $this->tenant);
        LegalHold::create([
            'holdable_type' => MessageThread::class,
            'holdable_id' => $thread->id,
            'reason' => 'Already preserved.',
            'held_by' => $this->landlord->id,
            'held_at' => now(),
        ]);

        $this->actingAs($this->landlord)
            ->getJson(route('legal-holds.subjects.suggest', ['tenant_id' => $this->tenant->id]))
            ->assertOk()
            ->assertJsonPath('groups.3.held', 1)
            ->assertJsonPath('groups.3.items.0.already_held', true);
    }

    public function test_cannot_suggest_for_another_landlords_tenant(): void
    {
        $this->actingAs($this->landlord)
            ->getJson(route('legal-holds.subjects.suggest', ['tenant_id' => $this->otherTenant->id]))
            ->assertForbidden();
    }

    public function test_non_landlord_cannot_reach_the_endpoint(): void
    {
        $this->actingAs($this->tenant)
            ->getJson(route('legal-holds.subjects.suggest', ['tenant_id' => $this->tenant->id]))
            ->assertForbidden();
    }
}
