<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Document;
use App\Models\LegalHold;
use App\Models\User;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-68 BULK-UI: the Documents bulk-hold grid drives the Phase-65
 * BulkHoldService endpoints with the Document subject type — place a
 * hold on many documents at once, and release them in bulk.
 */
class Phase68BulkUiTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    /**
     * @return array<int, int>
     */
    private function documents(int $n): array
    {
        return collect(range(1, $n))->map(fn () => Document::factory()->create([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => User::class,
            'documentable_id' => $this->landlord->id,
            'uploaded_by' => $this->landlord->id,
        ])->id)->all();
    }

    public function test_bulk_place_holds_on_selected_documents(): void
    {
        $ids = $this->documents(3);

        $this->actingAs($this->landlord)->post(route('legal-holds.bulk.store'), [
            'subject_type' => Document::class,
            'subject_ids' => $ids,
            'reason' => 'litigation preservation — bulk select',
        ])->assertRedirect();

        $this->assertSame(3, LegalHold::query()
            ->where('holdable_type', Document::class)
            ->whereNull('released_at')
            ->count());
    }

    public function test_bulk_release_clears_holds_on_selected_documents(): void
    {
        $ids = $this->documents(3);
        foreach ($ids as $id) {
            LegalHoldRegistry::hold(Document::find($id), $this->landlord, 'preservation order to be released');
        }

        $this->actingAs($this->landlord)->delete(route('legal-holds.bulk.destroy'), [
            'subject_type' => Document::class,
            'subject_ids' => $ids,
        ])->assertRedirect();

        $this->assertSame(0, LegalHold::query()
            ->where('holdable_type', Document::class)
            ->whereNull('released_at')
            ->count());
    }

    public function test_bulk_place_is_idempotent_on_already_held(): void
    {
        $ids = $this->documents(3);
        // First place holds on all three.
        foreach ($ids as $id) {
            LegalHoldRegistry::hold(Document::find($id), $this->landlord, 'first preservation order');
        }

        // Bulk place over the same set must NOT mint duplicate active rows
        // (MySQL NULL-distinct unique index would otherwise allow it).
        $this->actingAs($this->landlord)->post(route('legal-holds.bulk.store'), [
            'subject_type' => Document::class,
            'subject_ids' => $ids,
            'reason' => 'duplicate preservation attempt',
        ])->assertRedirect();

        $this->assertSame(3, LegalHold::query()
            ->where('holdable_type', Document::class)
            ->whereNull('released_at')
            ->count());
    }

    public function test_bulk_place_rejects_cross_tenant_documents(): void
    {
        $otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
        $theirDoc = Document::factory()->create([
            'landlord_id' => $otherLandlord->id,
            'documentable_type' => User::class,
            'documentable_id' => $otherLandlord->id,
            'uploaded_by' => $otherLandlord->id,
        ]);

        $this->actingAs($this->landlord)->post(route('legal-holds.bulk.store'), [
            'subject_type' => Document::class,
            'subject_ids' => [$theirDoc->id],
            'reason' => 'attempted cross-tenant bulk hold',
        ])->assertForbidden();

        $this->assertSame(0, LegalHold::query()->where('holdable_type', Document::class)->count());
    }
}
