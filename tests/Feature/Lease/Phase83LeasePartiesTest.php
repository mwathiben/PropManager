<?php

declare(strict_types=1);

namespace Tests\Feature\Lease;

use App\Models\Document;
use App\Models\Lease;
use App\Models\LeaseCoTenant;
use App\Models\LeaseGuarantor;
use App\Models\LeaseRenewal;
use App\Models\User;
use App\Services\Documents\DocumentGenerationService;
use App\Services\Lease\LeaseGuarantorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-83 CO-TENANT + GUARANTOR + LEASE-DOC-GEN.
 */
class Phase83LeasePartiesTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
    }

    public function test_co_tenant_add_remove_and_active_scope(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('lease-co-tenants.store', $this->lease->id), [
                'name' => 'Jane Co',
                'email' => 'jane@example.com',
                'is_responsible_for_rent' => true,
                'liability_share' => 50,
            ])
            ->assertRedirect();

        $this->assertCount(1, $this->lease->fresh()->coTenants);

        $coTenant = LeaseCoTenant::where('lease_id', $this->lease->id)->first();
        $this->actingAs($this->landlord)
            ->delete(route('lease-co-tenants.destroy', $coTenant->id))
            ->assertRedirect();

        $this->assertNotNull($coTenant->fresh()->removed_at);
        $this->assertCount(0, $this->lease->fresh()->coTenants);
    }

    public function test_guarantor_add_and_release(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('lease-guarantors.store', $this->lease->id), [
                'name' => 'Parent Guarantor',
                'guaranteed_amount' => 200000,
            ])
            ->assertRedirect();

        $guarantor = LeaseGuarantor::where('lease_id', $this->lease->id)->first();
        $this->assertSame(LeaseGuarantor::STATUS_ACTIVE, $guarantor->status);

        $this->actingAs($this->landlord)
            ->post(route('lease-guarantors.release', $guarantor->id))
            ->assertRedirect();

        $guarantor->refresh();
        $this->assertSame(LeaseGuarantor::STATUS_RELEASED, $guarantor->status);
        $this->assertNotNull($guarantor->released_at);
    }

    public function test_release_all_for_lease_releases_only_active(): void
    {
        Model::withoutEvents(function () {
            LeaseGuarantor::factory()->create(['lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id]);
            LeaseGuarantor::factory()->create(['lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id]);
            LeaseGuarantor::factory()->released()->create(['lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id]);
        });

        $released = app(LeaseGuarantorService::class)->releaseAllForLease($this->lease, 'move-out');

        $this->assertSame(2, $released);
        $this->assertSame(0, LeaseGuarantor::where('lease_id', $this->lease->id)->active()->count());
    }

    public function test_generate_lease_agreement_creates_document_with_parties(): void
    {
        Model::withoutEvents(function () {
            LeaseCoTenant::factory()->create(['lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id]);
            LeaseGuarantor::factory()->create(['lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id]);
        });

        app(DocumentGenerationService::class)->generateLeaseAgreement($this->lease->fresh(), $this->landlord);

        $this->assertTrue(
            Document::where('documentable_type', Lease::class)
                ->where('documentable_id', $this->lease->id)
                ->where('document_type', 'lease_agreement')->exists(),
        );
    }

    public function test_generate_renewal_offer_creates_document(): void
    {
        $renewal = Model::withoutEvents(fn () => LeaseRenewal::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $this->lease->id,
            'proposed_end_date' => now()->addYear()->toDateString(),
            'proposed_rent_amount_cents' => 1200000,
            'status' => LeaseRenewal::STATUS_PROPOSED,
            'proposed_at' => now(),
        ]));

        app(DocumentGenerationService::class)->generateRenewalOffer($renewal->fresh(), $this->landlord);

        $this->assertTrue(
            Document::where('documentable_type', Lease::class)
                ->where('documentable_id', $this->lease->id)
                ->where('title', __('lease_doc.renewal.title'))->exists(),
        );
    }
}
