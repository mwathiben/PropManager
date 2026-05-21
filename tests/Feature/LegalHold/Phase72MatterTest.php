<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\LegalHold;
use App\Models\LegalMatter;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-72 MATTER-GROUPING: case-level grouping, isolation, release, and the
 * close lifecycle gating.
 */
class Phase72MatterTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    // Create as the owning landlord so the TenantScope global scope registers
    // (it only attaches when a model first boots authenticated) AND the
    // creating-hook stamps the correct landlord_id — mirroring real requests.
    private function matter(User $owner, string $title = 'Case', string $status = LegalMatter::STATUS_OPEN): LegalMatter
    {
        $this->actingAs($owner);

        return LegalMatter::create(['title' => $title, 'status' => $status]);
    }

    private function holdInMatter(LegalMatter $matter, User $owner): LegalHold
    {
        $this->actingAs($owner);
        $thread = MessageThread::create(['landlord_id' => $owner->id]);

        return LegalHold::create([
            'legal_matter_id' => $matter->id,
            'holdable_type' => MessageThread::class,
            'holdable_id' => $thread->id,
            'reason' => 'Preserve for the case.',
            'held_by' => $owner->id,
            'held_at' => now(),
        ]);
    }

    public function test_index_excludes_other_landlords_matters(): void
    {
        $this->matter($this->landlord, 'Mine');
        $this->matter($this->otherLandlord, 'Theirs');

        $this->actingAs($this->landlord)
            ->get(route('legal-matters.index'))
            ->assertInertia(fn ($page) => $page
                ->component('LegalHolds/Matters/Index')
                ->has('matters.data', 1)
                ->where('matters.data.0.title', 'Mine'),
            );
    }

    public function test_show_lists_the_matters_holds(): void
    {
        $matter = $this->matter($this->landlord);
        $this->holdInMatter($matter, $this->landlord);
        $this->holdInMatter($matter, $this->landlord);

        $this->actingAs($this->landlord)
            ->get(route('legal-matters.show', $matter->id))
            ->assertInertia(fn ($page) => $page
                ->component('LegalHolds/Matters/Show')
                ->where('matter.active_count', 2)
                ->has('holds', 2),
            );
    }

    public function test_release_clears_only_this_matters_holds(): void
    {
        $matterA = $this->matter($this->landlord, 'A');
        $holdA1 = $this->holdInMatter($matterA, $this->landlord);
        $holdA2 = $this->holdInMatter($matterA, $this->landlord);

        $matterB = $this->matter($this->landlord, 'B');
        $holdB = $this->holdInMatter($matterB, $this->landlord);

        $this->actingAs($this->landlord)
            ->post(route('legal-matters.release', $matterA->id))
            ->assertRedirect(route('legal-matters.show', $matterA->id));

        $this->assertNotNull($holdA1->fresh()->released_at);
        $this->assertNotNull($holdA2->fresh()->released_at);
        $this->assertNull($holdB->fresh()->released_at);
    }

    public function test_matter_cannot_close_with_active_holds_then_closes_after_release(): void
    {
        $matter = $this->matter($this->landlord);
        $this->holdInMatter($matter, $this->landlord);

        // Close blocked while a hold is active.
        $this->actingAs($this->landlord)
            ->post(route('legal-matters.close', $matter->id))
            ->assertSessionHas('error');
        $this->assertSame(LegalMatter::STATUS_OPEN, $matter->fresh()->status);

        // Release, then close succeeds.
        $this->actingAs($this->landlord)->post(route('legal-matters.release', $matter->id));
        $this->actingAs($this->landlord)
            ->post(route('legal-matters.close', $matter->id))
            ->assertSessionHas('success');

        $matter->refresh();
        $this->assertSame(LegalMatter::STATUS_CLOSED, $matter->status);
        $this->assertNotNull($matter->closed_at);
        $this->assertSame($this->landlord->id, $matter->closed_by);
    }

    public function test_reopen_restores_open_status(): void
    {
        $matter = $this->matter($this->landlord, 'Closed case', LegalMatter::STATUS_CLOSED);
        $matter->forceFill(['closed_at' => now(), 'closed_by' => $this->landlord->id])->save();

        $this->actingAs($this->landlord)->post(route('legal-matters.reopen', $matter->id));

        $matter->refresh();
        $this->assertSame(LegalMatter::STATUS_OPEN, $matter->status);
        $this->assertNull($matter->closed_at);
    }

    public function test_cannot_view_another_landlords_matter(): void
    {
        $foreign = $this->matter($this->otherLandlord, 'Theirs');

        $this->actingAs($this->landlord)
            ->get(route('legal-matters.show', $foreign->id))
            ->assertNotFound();
    }

    public function test_matter_audit_export_redirects_to_signed_url(): void
    {
        Storage::fake('local');
        $matter = $this->matter($this->landlord);
        $this->holdInMatter($matter, $this->landlord);

        $this->actingAs($this->landlord)
            ->get(route('legal-matters.audit-export', $matter->id))
            ->assertRedirect();
    }

    public function test_cannot_act_on_another_landlords_matter(): void
    {
        Storage::fake('local');
        $foreign = $this->matter($this->otherLandlord, 'Theirs');

        $this->actingAs($this->landlord)->post(route('legal-matters.release', $foreign->id))->assertNotFound();
        $this->actingAs($this->landlord)->post(route('legal-matters.close', $foreign->id))->assertNotFound();
        $this->actingAs($this->landlord)->post(route('legal-matters.reopen', $foreign->id))->assertNotFound();
        $this->actingAs($this->landlord)->get(route('legal-matters.audit-export', $foreign->id))->assertNotFound();
    }
}
