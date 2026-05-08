<?php

namespace Tests\Browser;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BulkPaymentImportTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $landlord;

    protected Building $building;

    protected Unit $unit;

    protected Lease $lease;

    protected User $tenant;

    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create([
            'role' => 'landlord',
            'email' => 'bulk-test@landlord.com',
            'password' => bcrypt('password'),
        ]);

        $property = Property::create([
            'name' => 'Test Property',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->building = Building::create([
            'property_id' => $property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Block A',
            'floors' => 2,
        ]);

        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'landlord_id' => $this->landlord->id,
            'unit_number' => 'A101',
            'floor' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
        ]);

        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'email' => 'tenant@test.com',
        ]);

        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now()->subMonth(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);

        $this->invoice = Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-202602-0001',
            'rent_due' => 25000,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
        ]);

        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_xxxxx',
            'paystack_secret_key' => 'sk_test_xxxxx',
        ]);
    }

    public function test_bulk_import_page_renders_with_required_elements(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/finances/payments/bulk-import')
                ->waitForText('Bulk Import Payments')
                ->assertSee('Bulk Import Payments')
                ->assertSee('Import Mode')
                ->assertSee('Block A')
                ->assertPresent('#csv-file');
        });
    }

    public function test_bulk_import_template_download(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/finances/payments/bulk-import')
                ->waitForText('Bulk Import Payments')
                ->assertSeeLink('Download Current Template');
        });
    }

    public function test_bulk_import_mode_toggle_shows_historical_warning(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/finances/payments/bulk-import')
                ->waitForText('Bulk Import Payments')
                ->clickLink('Historical')
                ->waitForText('Historical Import Mode')
                ->assertSee('Historical Import Mode');
        });
    }

    public function test_bulk_import_requires_file_and_building(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/finances/payments/bulk-import')
                ->waitForText('Bulk Import Payments')
                ->assertDisabled('@validate-button');
        });
    }
}
