<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Part;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-75 PARTS-PRICING: price-history append on cost change + supplier-per-part
 * (cheapest/fastest) + cross-tenant supplier rejection.
 */
class Phase75PartsPricingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->actingAs($this->landlord);
    }

    private function part(int $cost = 1000): Part
    {
        return Part::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Pipe',
            'category' => 'plumbing',
            'cost_per_unit_cents' => $cost,
            'qty_available' => 10,
            'reorder_threshold' => 3,
            'is_active' => true,
        ]);
    }

    private function vendor(User $owner, string $name): Vendor
    {
        return Vendor::create(['landlord_id' => $owner->id, 'name' => $name, 'is_active' => true]);
    }

    public function test_price_history_appends_on_create_and_cost_change(): void
    {
        $part = $this->part(1000);
        $this->assertSame(1, $part->priceHistory()->count());

        $part->update(['cost_per_unit_cents' => 1500]);
        $this->assertSame(2, $part->fresh()->priceHistory()->count());

        // A non-cost change does not append.
        $part->update(['name' => 'Copper Pipe']);
        $this->assertSame(2, $part->fresh()->priceHistory()->count());

        $this->assertSame(1500, $part->fresh()->priceHistory()->first()->cost_per_unit_cents);
    }

    public function test_cheapest_and_fastest_supplier(): void
    {
        $part = $this->part();
        $a = $this->vendor($this->landlord, 'Cheap & Slow');
        $b = $this->vendor($this->landlord, 'Pricey & Fast');

        $this->post(route('parts.suppliers.store', $part->id), ['vendor_id' => $a->id, 'unit_cost_cents' => 500, 'lead_time_days' => 10, 'min_order_qty' => 1])->assertRedirect();
        $this->post(route('parts.suppliers.store', $part->id), ['vendor_id' => $b->id, 'unit_cost_cents' => 800, 'lead_time_days' => 3, 'min_order_qty' => 1])->assertRedirect();

        $this->assertSame($a->id, $part->fresh()->cheapestSupplier()->vendor_id);
        $this->assertSame($b->id, $part->fresh()->fastestSupplier()->vendor_id);
    }

    public function test_store_supplier_upserts(): void
    {
        $part = $this->part();
        $vendor = $this->vendor($this->landlord, 'Acme');

        $this->post(route('parts.suppliers.store', $part->id), ['vendor_id' => $vendor->id, 'unit_cost_cents' => 500, 'lead_time_days' => 5, 'min_order_qty' => 2])->assertRedirect();
        $this->post(route('parts.suppliers.store', $part->id), ['vendor_id' => $vendor->id, 'unit_cost_cents' => 600, 'lead_time_days' => 4, 'min_order_qty' => 1])->assertRedirect();

        $this->assertSame(1, $part->fresh()->suppliers()->count());
        $this->assertSame(600, $part->fresh()->cheapestSupplier()->unit_cost_cents);
    }

    public function test_store_supplier_rejects_a_cross_tenant_vendor(): void
    {
        $part = $this->part();

        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup()['landlord']);
        $this->actingAs($other);
        $foreignVendor = $this->vendor($other, 'Theirs');
        $this->actingAs($this->landlord);

        $this->post(route('parts.suppliers.store', $part->id), ['vendor_id' => $foreignVendor->id, 'unit_cost_cents' => 500, 'lead_time_days' => 5, 'min_order_qty' => 1])
            ->assertSessionHasErrors('vendor_id');

        $this->assertSame(0, $part->fresh()->suppliers()->count());
    }
}
