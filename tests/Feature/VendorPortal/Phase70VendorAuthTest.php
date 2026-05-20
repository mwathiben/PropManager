<?php

declare(strict_types=1);

namespace Tests\Feature\VendorPortal;

use App\Mail\VendorPortalLinkMailable;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Vendors\VendorPortalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-70 VENDOR-AUTH: the signed magic-link establishes a vendor-portal
 * session; EnsureVendorPortal guards the portal and re-checks the vendor
 * is active each request. Landlord re-issue is owner-gated.
 */
class Phase70VendorAuthTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(bool $active = true, ?User $landlord = null): Vendor
    {
        $landlord ??= User::factory()->create(['role' => 'landlord']);

        return Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'email' => 'acme@contractor.test',
            'is_active' => $active,
        ]);
    }

    public function test_signed_enter_link_sets_session_and_redirects(): void
    {
        $vendor = $this->vendor();
        $url = app(VendorPortalLinkService::class)->issue($vendor);

        $this->get($url)
            ->assertRedirect(route('vendor.portal.dashboard'))
            ->assertSessionHas('vendor_portal_id', $vendor->id);
    }

    public function test_tampered_enter_link_is_rejected(): void
    {
        $vendor = $this->vendor();

        // No signature -> the `signed` middleware rejects.
        $this->get(route('vendor.portal.enter', $vendor))->assertForbidden();
    }

    public function test_deactivated_vendor_cannot_enter(): void
    {
        $vendor = $this->vendor(active: false);
        $url = app(VendorPortalLinkService::class)->issue($vendor);

        $this->get($url)->assertForbidden();
        $this->assertNull(session('vendor_portal_id'));
    }

    public function test_portal_requires_a_session(): void
    {
        $this->get('/v/portal')->assertForbidden();
    }

    public function test_portal_dashboard_works_with_session(): void
    {
        $vendor = $this->vendor();

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->get('/v/portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('VendorPortal/Dashboard')
                ->where('vendor.id', $vendor->id));
    }

    public function test_deactivated_vendor_loses_access_mid_session(): void
    {
        $vendor = $this->vendor(active: false);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->get('/v/portal')
            ->assertForbidden();
    }

    public function test_link_service_mints_a_verifiable_signed_url(): void
    {
        $vendor = $this->vendor();
        $url = app(VendorPortalLinkService::class)->issue($vendor);

        // A valid signature round-trips through the signed middleware.
        $this->get($url)->assertRedirect(route('vendor.portal.dashboard'));
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_landlord_reissue_is_owner_gated(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $theirVendor = $this->vendor(landlord: $landlordB);
        $ownVendor = $this->vendor(landlord: $landlordA);

        // Fake AFTER creation so VendorObserver's welcome email isn't counted.
        Mail::fake();

        // VendorPolicy::update denies a cross-tenant vendor -> 403, no link.
        $this->actingAs($landlordA)
            ->post(route('finances.vendors.portal-link', $theirVendor))
            ->assertForbidden();
        Mail::assertNotQueued(VendorPortalLinkMailable::class);

        $this->actingAs($landlordA)
            ->post(route('finances.vendors.portal-link', $ownVendor))
            ->assertRedirect();
        Mail::assertQueued(
            VendorPortalLinkMailable::class,
            fn (VendorPortalLinkMailable $m) => $m->vendor->id === $ownVendor->id,
        );
    }

    public function test_logout_clears_the_session(): void
    {
        $vendor = $this->vendor();

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post('/v/portal/logout')
            ->assertRedirect();
        $this->assertNull(session('vendor_portal_id'));
    }
}
