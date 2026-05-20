<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\MessageThread;
use App\Models\User;
use App\Services\Inbox\MessageSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase-67 MESSAGE-SEARCH CI: full-text search is strictly confined to
 * the caller's own threads (no same-landlord or cross-tenant leak),
 * boolean operators are neutralised, and the short-query guard holds.
 */
class Phase67MessageSearchTest extends TestCase
{
    use RefreshDatabase;

    private function threadWithMessage(User $landlord, User $participant, string $body): MessageThread
    {
        $thread = MessageThread::create(['landlord_id' => $landlord->id, 'title' => 'Maintenance']);
        $thread->participants()->attach($landlord->id, ['role' => 'landlord']);
        $thread->participants()->attach($participant->id, ['role' => 'tenant']);
        $thread->messages()->create(['sender_id' => $participant->id, 'body' => $body]);

        return $thread;
    }

    private function service(): MessageSearchService
    {
        return app(MessageSearchService::class);
    }

    public function test_participant_finds_their_match(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $thread = $this->threadWithMessage($landlord, $tenant, 'The quarterly inspection report is ready');

        $results = $this->service()->search($landlord, 'inspection');

        $this->assertSame(1, $results->total());
        $this->assertSame($thread->id, $results->items()[0]['thread_id']);
        $this->assertStringContainsString('inspection', $results->items()[0]['snippet']);
    }

    public function test_non_participant_same_landlord_cannot_see_match(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $outsider = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $this->threadWithMessage($landlord, $tenant, 'The quarterly inspection report is ready');

        $this->assertSame(0, $this->service()->search($outsider, 'inspection')->total());
    }

    public function test_cross_tenant_isolation(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $tenantA = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlordA->id]);
        $this->threadWithMessage($landlordA, $tenantA, 'The quarterly inspection report');

        $landlordB = User::factory()->create(['role' => 'landlord']);

        $this->assertSame(0, $this->service()->search($landlordB, 'inspection')->total());
    }

    public function test_boolean_operators_are_neutralised(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $thread = $this->threadWithMessage($landlord, $tenant, 'The quarterly inspection report');

        // Crafted boolean-mode operators must not error and must still match.
        $results = $this->service()->search($landlord, '+inspection ~report "((');

        $this->assertSame(1, $results->total());
        $this->assertSame($thread->id, $results->items()[0]['thread_id']);
    }

    public function test_short_query_returns_empty(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $this->threadWithMessage($landlord, $tenant, 'inspection report');

        $this->assertSame(0, $this->service()->search($landlord, 'in')->total());
    }

    public function test_search_route_scopes_to_caller(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $this->threadWithMessage($landlord, $tenant, 'quarterly inspection report');

        $this->actingAs($landlord)
            ->get(route('message-threads.search', ['q' => 'inspection']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MessageThreads/Search')
                ->where('q', 'inspection')
                ->has('results'));
    }
}
