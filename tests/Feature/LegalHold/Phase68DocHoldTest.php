<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Document;
use App\Models\User;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-68 DOC-HOLD-1: the Documents index surfaces per-row legal-hold
 * state (is_held + legal_hold_id) to the landlord/super-admin only, so
 * the Vue layer can place/release holds on the only ALLOWED_HOLDABLE_TYPES
 * subject without a Show page.
 */
class Phase68DocHoldTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function document(): Document
    {
        return Document::factory()->create([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => User::class,
            'documentable_id' => $this->landlord->id,
            'uploaded_by' => $this->landlord->id,
        ]);
    }

    public function test_landlord_sees_held_document_flagged_with_hold_id(): void
    {
        $document = $this->document();
        $hold = LegalHoldRegistry::hold($document, $this->landlord, 'preservation order CV/2026/0042');

        $this->actingAs($this->landlord)->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->where('documents.data.0.id', $document->id)
                ->where('documents.data.0.is_held', true)
                ->where('documents.data.0.legal_hold_id', $hold->id)
            );
    }

    public function test_landlord_sees_unheld_document_not_flagged(): void
    {
        $document = $this->document();

        $this->actingAs($this->landlord)->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('documents.data.0.id', $document->id)
                ->where('documents.data.0.is_held', false)
                ->where('documents.data.0.legal_hold_id', null)
            );
    }

    public function test_caretaker_does_not_see_hold_state(): void
    {
        $document = $this->document();
        LegalHoldRegistry::hold($document, $this->landlord, 'preservation order CV/2026/0043');

        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->actingAs($caretaker)->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('documents.data.0.is_held', false)
                ->where('documents.data.0.legal_hold_id', null)
            );
    }
}
