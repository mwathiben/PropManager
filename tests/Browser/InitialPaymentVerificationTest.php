<?php

namespace Tests\Browser;

use App\Models\Lease;
use App\Models\PaymentConfiguration;
use App\Models\TenantPaymentVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\CreatesTestData;

class InitialPaymentVerificationTest extends DuskTestCase
{
    use CreatesTestData, DatabaseMigrations;

    protected User $landlord;

    protected User $tenant;

    protected Lease $lease;

    protected TenantPaymentVerification $verification;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $unit = $setup['units']->first();

        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'password' => bcrypt('password'),
        ]);

        $this->lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now()->subMonth(),
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'is_active' => true,
        ]);

        $unit->update(['status' => 'occupied']);

        $this->verification = TenantPaymentVerification::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'status' => TenantPaymentVerification::STATUS_PENDING_PAYMENT,
            'deposit_required' => 25000,
            'first_rent_required' => 25000,
            'other_charges' => 0,
            'total_required' => 50000,
            'amount_paid' => 0,
        ]);

        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash', 'bank_transfer'],
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_dummy',
            'paystack_secret_key' => 'sk_test_dummy',
        ]);
    }

    public function test_unverified_tenant_sees_payment_required_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->tenant)
                ->visit('/tenant/payment-required')
                ->waitForText('Payment Required')
                ->assertSee('Payment Required');
        });
    }

    public function test_payment_required_shows_correct_amounts(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->tenant)
                ->visit('/tenant/payment-required')
                ->waitForText('Payment Required')
                ->assertSee('25,000')
                ->assertSee('50,000');
        });
    }

    public function test_verified_tenant_redirected_to_dashboard(): void
    {
        $this->verification->update([
            'status' => TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
            'amount_paid' => 50000,
            'verified_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->tenant)
                ->visit('/tenant/payment-required')
                ->waitForLocation('/dashboard')
                ->assertPathIs('/dashboard');
        });
    }
}
