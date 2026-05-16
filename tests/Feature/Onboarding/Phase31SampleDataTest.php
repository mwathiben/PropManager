<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SampleDataRun;
use App\Models\Unit;
use App\Models\User;
use App\Services\Onboarding\SampleDataSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase31SampleDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_populate_creates_full_demo_dataset(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $run = app(SampleDataSeederService::class)->populate($landlord);

        $this->assertNotNull($run);
        $this->assertSame(SampleDataRun::STATUS_POPULATED, $run->status);
        $refs = $run->row_refs;
        $this->assertCount(1, $refs['properties']);
        $this->assertCount(1, $refs['buildings']);
        $this->assertCount(4, $refs['units']);
        $this->assertCount(2, $refs['tenants']);
        $this->assertCount(2, $refs['leases']);
        $this->assertCount(6, $refs['invoices']);
        $this->assertCount(4, $refs['payments']);
    }

    public function test_sample_tenants_use_propmanager_demo_email_tld(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        app(SampleDataSeederService::class)->populate($landlord);

        $tenants = User::query()
            ->where('landlord_id', $landlord->id)
            ->where('role', 'tenant')
            ->get();

        $this->assertCount(2, $tenants);
        foreach ($tenants as $tenant) {
            $this->assertStringEndsWith('@propmanager.demo', $tenant->email);
        }
    }

    public function test_populate_refuses_when_landlord_has_active_lease(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'name' => 'Real Property', 'address' => 'Real St', 'type' => 'apartment', 'landlord_id' => $landlord->id,
        ]);
        $building = Building::create([
            'property_id' => $property->id, 'landlord_id' => $landlord->id,
            'name' => 'Real Block', 'total_floors' => 1, 'units_per_floor' => 1,
            'building_type' => 'residential_apartment',
        ]);
        $unit = Unit::create([
            'building_id' => $building->id, 'landlord_id' => $landlord->id,
            'unit_number' => 'R01', 'floor_number' => 1, 'status' => 'occupied', 'target_rent' => 30000,
        ]);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'landlord_id' => $landlord->id,
            'rent_amount' => 30000, 'deposit_amount' => 30000, 'start_date' => now(),
            'is_active' => true, 'wallet_balance' => 0,
        ]);

        $run = app(SampleDataSeederService::class)->populate($landlord);
        $this->assertNull($run);
    }

    public function test_reset_removes_only_sample_rows_and_marks_run_done(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeder = app(SampleDataSeederService::class);

        $run = $seeder->populate($landlord);
        $this->assertNotNull($run);

        $sampleInvoiceIds = $run->row_refs['invoices'];

        $reset = $seeder->reset($landlord);
        $this->assertSame(1, $reset);
        $run->refresh();
        $this->assertSame(SampleDataRun::STATUS_RESET_DONE, $run->status);
        $this->assertNotNull($run->reset_at);

        foreach ($sampleInvoiceIds as $id) {
            $this->assertDatabaseMissing('invoices', ['id' => $id, 'deleted_at' => null]);
        }
    }

    public function test_reset_is_idempotent_when_no_populated_run(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->assertSame(0, app(SampleDataSeederService::class)->reset($landlord));
    }

    public function test_controller_populate_endpoint_creates_run(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('onboarding.sample.populate'))
            ->assertRedirect();

        $this->assertDatabaseHas('sample_data_runs', [
            'landlord_id' => $landlord->id,
            'status' => SampleDataRun::STATUS_POPULATED,
        ]);
    }

    public function test_controller_reset_endpoint_flips_run_status(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        app(SampleDataSeederService::class)->populate($landlord);

        $this->actingAs($landlord)
            ->post(route('onboarding.sample.reset'))
            ->assertRedirect();

        $this->assertDatabaseHas('sample_data_runs', [
            'landlord_id' => $landlord->id,
            'status' => SampleDataRun::STATUS_RESET_DONE,
        ]);
    }

    public function test_tenant_cannot_populate_sample_data(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $this->actingAs($tenant)
            ->post(route('onboarding.sample.populate'))
            ->assertForbidden();
    }
}
