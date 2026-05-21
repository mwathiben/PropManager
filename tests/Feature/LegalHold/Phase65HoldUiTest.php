<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Invoice;
use App\Models\User;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-65 HOLD-UI watchdog: LegalHoldController CRUD + cross-tenant
 * isolation + nav badge wiring + sw/ar key parity.
 */
class Phase65HoldUiTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $a = $this->createLandlordWithFullSetup();
        $this->landlord = $a['landlord'];

        $b = $this->createLandlordWithFullSetup();
        $this->otherLandlord = $b['landlord'];
    }

    public function test_index_lists_only_acting_landlord_holds(): void
    {
        $myInvoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $theirInvoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);

        LegalHoldRegistry::hold($myInvoice, $this->landlord, 'preservation order: my landlord');
        LegalHoldRegistry::hold($theirInvoice, $this->otherLandlord, 'preservation order: other landlord');

        // Phase-72 COMMAND-CENTER: the flat list moved to legal-holds.list;
        // legal-holds.index is now the command-center home.
        $response = $this->actingAs($this->landlord)->get(route('legal-holds.list'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('LegalHolds/Index')
            ->where('holds.data.0.holdable_id', $myInvoice->id)
            ->where('holds.data.0.reason', 'preservation order: my landlord')
        );
    }

    public function test_index_filters_by_subject_type(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        LegalHoldRegistry::hold($invoice, $this->landlord, 'reason long enough');

        $response = $this->actingAs($this->landlord)->get(route('legal-holds.list', [
            'subject_type' => Invoice::class,
        ]));

        $response->assertOk();
    }

    public function test_store_creates_hold_with_valid_reason(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);

        $response = $this->actingAs($this->landlord)->post(route('legal-holds.store'), [
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
            'reason' => 'Court order CV/2026/0123 — preservation directive',
        ]);

        // Phase-72 COMMAND-CENTER: store now lands on the flat list (the home
        // is the command-center).
        $response->assertRedirect(route('legal-holds.list'));
        $this->assertDatabaseHas('legal_holds', [
            'holdable_type' => Invoice::class,
            'holdable_id' => $invoice->id,
            'held_by' => $this->landlord->id,
        ]);
    }

    public function test_store_rejects_cross_landlord_subject(): void
    {
        $theirInvoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);

        $response = $this->actingAs($this->landlord)->post(route('legal-holds.store'), [
            'subject_type' => Invoice::class,
            'subject_id' => $theirInvoice->id,
            'reason' => 'attempting cross-landlord hold injection',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('legal_holds', [
            'holdable_id' => $theirInvoice->id,
        ]);
    }

    public function test_store_rejects_short_reason(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);

        $response = $this->actingAs($this->landlord)
            ->from(route('legal-holds.index'))
            ->post(route('legal-holds.store'), [
                'subject_type' => Invoice::class,
                'subject_id' => $invoice->id,
                'reason' => 'too short',
            ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_destroy_releases_hold(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $hold = LegalHoldRegistry::hold($invoice, $this->landlord, 'reason long enough for test');

        $response = $this->actingAs($this->landlord)
            ->delete(route('legal-holds.destroy', $hold));

        $response->assertRedirect();
        $this->assertNotNull($hold->fresh()->released_at);
        $this->assertSame($this->landlord->id, (int) $hold->fresh()->released_by);
    }

    public function test_destroy_rejects_cross_landlord(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);
        $hold = LegalHoldRegistry::hold($invoice, $this->otherLandlord, 'their hold');

        $response = $this->actingAs($this->landlord)
            ->delete(route('legal-holds.destroy', $hold));

        $response->assertForbidden();
        $this->assertNull($hold->fresh()->released_at);
    }

    public function test_active_count_for_landlord_returns_per_landlord_total(): void
    {
        $myInvoiceA = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $myInvoiceB = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $theirInvoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);

        LegalHoldRegistry::hold($myInvoiceA, $this->landlord, 'reason a long enough');
        LegalHoldRegistry::hold($myInvoiceB, $this->landlord, 'reason b long enough');
        LegalHoldRegistry::hold($theirInvoice, $this->otherLandlord, 'reason c long enough');

        Cache::flush();

        $this->assertSame(2, LegalHoldRegistry::activeCountForLandlord($this->landlord->id));
        $this->assertSame(1, LegalHoldRegistry::activeCountForLandlord($this->otherLandlord->id));
    }

    public function test_legal_holds_nav_key_present_in_three_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $contents = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertArrayHasKey('legal_holds', $contents['nav'] ?? [], "{$locale}.json missing nav.legal_holds");
        }
    }

    public function test_route_legal_holds_index_requires_landlord_role(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $response = $this->actingAs($tenant)->get(route('legal-holds.index'));
        $response->assertForbidden();
    }
}
