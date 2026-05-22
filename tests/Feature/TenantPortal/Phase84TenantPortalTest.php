<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseCoTenant;
use App\Models\LeaseGuarantor;
use App\Models\LeaseRenewal;
use App\Models\TenantPaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-84 TENANT-PORTAL-DEPTH: payment-method self-management, renewal review,
 * lease visibility of Phase-83 data, per-invoice PDF download.
 */
class Phase84TenantPortalTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $bundle = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0)));
        $this->tenant = $bundle['tenant'];
        $this->lease = $bundle['lease'];
    }

    public function test_tenant_adds_sets_default_and_removes_payment_method(): void
    {
        $this->actingAs($this->tenant)
            ->post(route('tenant.payment-methods.store'), [
                'type' => 'mpesa',
                'phone' => '+254712345678',
                'is_default' => true,
            ])
            ->assertRedirect();

        $method = TenantPaymentMethod::where('user_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('mpesa', $method->type);
        $this->assertTrue($method->is_default);
        $this->assertSame('+254712345678', $method->details_encrypted['phone']);

        $this->actingAs($this->tenant)
            ->delete(route('tenant.payment-methods.destroy', $method->id))
            ->assertRedirect();
        $this->assertSoftDeleted('tenant_payment_methods', ['id' => $method->id]);
    }

    public function test_payment_method_validation_is_per_type(): void
    {
        $this->actingAs($this->tenant)
            ->from(route('tenant.payment-methods.index'))
            ->post(route('tenant.payment-methods.store'), ['type' => 'bank']) // missing bank fields
            ->assertSessionHasErrors(['bank_name', 'account_name', 'account_number']);

        $this->assertDatabaseCount('tenant_payment_methods', 0);
    }

    public function test_tenant_cannot_touch_another_tenants_method(): void
    {
        $otherTenant = Model::withoutEvents(fn () => User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id]));
        $otherMethod = Model::withoutEvents(fn () => TenantPaymentMethod::create([
            'user_id' => $otherTenant->id,
            'type' => 'mpesa',
            'details_encrypted' => ['phone' => '+254700000000'],
            'is_default' => true,
        ]));

        $this->actingAs($this->tenant)
            ->delete(route('tenant.payment-methods.destroy', $otherMethod->id))
            ->assertForbidden();
    }

    public function test_renewal_page_shows_offer_and_accept_transitions(): void
    {
        $renewal = Model::withoutEvents(fn () => LeaseRenewal::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $this->lease->id,
            'proposed_end_date' => now()->addYear()->toDateString(),
            'proposed_rent_amount_cents' => 1300000,
            'status' => LeaseRenewal::STATUS_PROPOSED,
            'proposed_at' => now(),
        ]));

        $this->actingAs($this->tenant)
            ->get(route('tenant.renewals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Renewals')
                ->where('renewal.id', $renewal->id)
                ->where('renewal.proposed_rent', 13000)
            );

        $this->actingAs($this->tenant)
            ->post(route('tenant.renewals.accept', $renewal->id))
            ->assertRedirect();
        $this->assertSame(LeaseRenewal::STATUS_ACCEPTED, $renewal->fresh()->status);
    }

    public function test_lease_page_surfaces_co_tenants_guarantors_and_renewal(): void
    {
        Model::withoutEvents(function () {
            LeaseCoTenant::factory()->create(['lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id]);
            LeaseGuarantor::factory()->create(['lease_id' => $this->lease->id, 'landlord_id' => $this->landlord->id]);
            LeaseRenewal::create([
                'landlord_id' => $this->landlord->id,
                'lease_id' => $this->lease->id,
                'proposed_end_date' => now()->addYear()->toDateString(),
                'proposed_rent_amount_cents' => 1300000,
                'status' => LeaseRenewal::STATUS_PROPOSED,
                'proposed_at' => now(),
            ]);
        });

        $this->actingAs($this->tenant)
            ->get(route('tenant.lease'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Lease')
                ->has('coTenants', 1)
                ->has('guarantors', 1)
                ->has('activeRenewal')
            );
    }

    public function test_tenant_downloads_own_invoice_pdf_but_not_others(): void
    {
        $invoice = Model::withoutEvents(fn () => $this->createInvoiceForLease($this->lease));

        $this->actingAs($this->tenant)
            ->get(route('tenant.invoices.download', $invoice->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $otherSetup = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup());
        $otherBundle = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($otherSetup['landlord'], $otherSetup['units']->get(0)));
        $otherInvoice = Model::withoutEvents(fn () => $this->createInvoiceForLease($otherBundle['lease']));

        $resp = $this->actingAs($this->tenant)->get(route('tenant.invoices.download', $otherInvoice->id));
        $this->assertContains($resp->status(), [403, 404]);
    }
}
