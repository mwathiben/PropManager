<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Building;
use App\Models\Document;
use App\Models\Meter;
use App\Models\Notification;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\Water\WaterComplianceService;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-92 WATER-COMPLIANCE: borehole compliance docs (reusing Phase-82 lifecycle
 * + expiry scan), abstraction-limit vs used, and the landlord-only compliance tab.
 */
class Phase92WaterComplianceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    private Building $building;

    private WaterComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];
        $this->building = $setup['building'];
        $this->building->update([
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
            'water_source' => 'borehole',
        ]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);

        $this->service = app(WaterComplianceService::class);
    }

    private function reading(Unit $unit, float $consumption, ?string $date = null): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->forUnit($unit)->create([
            'version' => 1,
            'reading_date' => $date ?? now()->toDateString(),
            'previous_reading' => 0,
            'current_reading' => $consumption,
            'consumption' => $consumption,
            'cost' => 0,
            'status' => 'approved',
            'is_invoiced' => false,
        ]));
    }

    private function buildingDoc(string $type, ?string $expiresAt, array $extra = []): Document
    {
        return Document::factory()->create(array_merge([
            'landlord_id' => $this->landlord->id,
            'uploaded_by' => $this->landlord->id,
            'documentable_type' => 'App\\Models\\Building',
            'documentable_id' => $this->building->id,
            'document_type' => $type,
            'is_renewable' => true,
            'reminder_days' => 30,
            'expires_at' => $expiresAt,
        ], $extra));
    }

    // --- COMPLIANCE DOCS -------------------------------------------------

    public function test_landlord_uploads_a_compliance_permit_to_a_building(): void
    {
        Storage::fake('local');

        $this->actingAs($this->landlord->fresh())
            ->post(route('documents.store'), [
                'file' => UploadedFile::fake()->image('permit.png'),
                'title' => 'WRA Permit 2026',
                'document_type' => 'wra_abstraction_permit',
                'documentable_type' => 'Building',
                'documentable_id' => $this->building->id,
                'expires_at' => now()->addYear()->toDateString(),
                'is_renewable' => true,
                'reminder_days' => 30,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'documentable_type' => 'App\\Models\\Building',
            'documentable_id' => $this->building->id,
            'document_type' => 'wra_abstraction_permit',
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_cannot_upload_to_another_landlords_building(): void
    {
        Storage::fake('local');
        $other = $this->createLandlordWithFullSetup();

        $response = $this->actingAs($this->landlord->fresh())
            ->post(route('documents.store'), [
                'file' => UploadedFile::fake()->image('permit.png'),
                'title' => 'Sneaky',
                'document_type' => 'wra_abstraction_permit',
                'documentable_type' => 'Building',
                'documentable_id' => $other['building']->id,
            ]);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseMissing('documents', ['documentable_id' => $other['building']->id, 'documentable_type' => 'App\\Models\\Building']);
    }

    public function test_expiring_building_permit_notifies_landlord_via_existing_scan(): void
    {
        $this->buildingDoc('wra_abstraction_permit', now()->addDays(10)->toDateString());

        $this->assertSame(0, Artisan::call('documents:scan-expiring'));

        $this->assertTrue(
            Notification::where('recipient_id', $this->landlord->id)
                ->where('type', Notification::TYPE_DOCUMENT_EXPIRY)
                ->exists()
        );
    }

    // --- ABSTRACTION LIMIT ----------------------------------------------

    public function test_landlord_sets_abstraction_limit(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->put(route('water.compliance.limit', $this->building->id), ['water_abstraction_limit' => 5000])
            ->assertRedirect();

        $this->assertEquals(5000, (float) $this->building->fresh()->water_abstraction_limit);
    }

    public function test_caretaker_cannot_set_abstraction_limit(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $this->actingAs($caretaker->fresh())
            ->put(route('water.compliance.limit', $this->building->id), ['water_abstraction_limit' => 5000])
            ->assertForbidden();
    }

    // --- ABSTRACTION USED + STATUS --------------------------------------

    public function test_service_computes_used_and_exceeded_status(): void
    {
        $this->building->update(['water_abstraction_limit' => 100]);
        $this->reading($this->units->get(0), 90);
        $this->reading($this->units->get(1), 60);

        $row = $this->buildingRow();

        $this->assertSame(150, $row['abstraction']['used']);
        $this->assertSame('exceeded', $row['abstraction']['status']);
        $this->assertSame('units', $row['abstraction']['basis']);
    }

    public function test_service_status_no_limit_and_unknown(): void
    {
        // No limit set, no readings.
        $row = $this->buildingRow();
        $this->assertSame('no_limit', $row['abstraction']['status']);
        $this->assertFalse($row['abstraction']['has_data']);

        // Limit set but still no readings -> unknown, not a fake "0% used / ok".
        $this->building->update(['water_abstraction_limit' => 100]);
        $row = $this->buildingRow();
        $this->assertSame('unknown', $row['abstraction']['status']);
        $this->assertFalse($row['abstraction']['has_data']);
        $this->assertNull($row['abstraction']['projected_annual']);
    }

    public function test_unit_basis_is_flagged_estimate_and_will_not_read_ok_when_high(): void
    {
        $this->building->update(['water_abstraction_limit' => 100]);
        // 80 of 100 on a unit-meter estimate (a lower bound) -> not a confident "ok".
        $this->reading($this->units->get(0), 80);

        $row = $this->buildingRow();
        $this->assertTrue($row['abstraction']['estimate']);
        $this->assertSame('units', $row['abstraction']['basis']);
        $this->assertNotSame('ok', $row['abstraction']['status']);
    }

    public function test_undated_permit_is_not_treated_as_compliant(): void
    {
        $this->building->update(['water_abstraction_limit' => 10000]);
        $this->reading($this->units->get(0), 100);
        // Permit on file but with NO expiry recorded -> validity unknown -> warning.
        $this->buildingDoc('wra_abstraction_permit', null);
        $this->buildingDoc('water_quality_certificate', now()->addYear()->toDateString());

        $row = $this->buildingRow();
        $this->assertSame('warning', $row['overall_status']);
    }

    public function test_abstraction_prefers_main_meter(): void
    {
        $this->building->update(['water_abstraction_limit' => 10000]);
        // Main meter = a top-level meter that feeds sub-meters (the abstraction
        // point). It carries a unit because readings require a unit_id.
        $main = Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $this->units->get(0)->id,
            'parent_meter_id' => null,
            'status' => 'active',
        ]);
        Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $this->units->get(1)->id,
            'parent_meter_id' => $main->id,
            'status' => 'active',
        ]);
        Model::withoutEvents(fn () => WaterReading::factory()->forMeter($main)->create([
            'version' => 1,
            'reading_date' => now()->toDateString(),
            'previous_reading' => 0,
            'current_reading' => 800,
            'consumption' => 800,
            'cost' => 0,
            'status' => 'approved',
        ]));
        // A plain unit reading exists too, but the main meter is authoritative.
        $this->reading($this->units->get(2), 200);

        $row = $this->buildingRow();
        $this->assertSame('meter', $row['abstraction']['basis']);
        $this->assertSame(800, $row['abstraction']['used']);
    }

    public function test_service_lists_only_borehole_buildings(): void
    {
        $county = Building::factory()->create([
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->building->property_id,
            'water_source' => 'county',
        ]);

        $ids = collect($this->service->forLandlord($this->landlord->id)['buildings'])->pluck('building_id')->all();

        $this->assertContains($this->building->id, $ids);
        $this->assertNotContains($county->id, $ids);
    }

    // --- TAB ROLE GATE ---------------------------------------------------

    public function test_landlord_opens_the_compliance_tab(): void
    {
        $props = $this->actingAs($this->landlord->fresh())
            ->get(route('water.hub', ['tab' => 'compliance']))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('compliance', $props['activeTab']);
        $this->assertArrayHasKey('compliance', $props);
        $this->assertArrayHasKey('summary', $props['compliance']);
    }

    public function test_caretaker_cannot_open_the_compliance_tab(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $props = $this->actingAs($caretaker->fresh())
            ->get(route('water.hub', ['tab' => 'compliance']))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('overview', $props['activeTab']);
        $this->assertArrayNotHasKey('compliance', $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildingRow(): array
    {
        $buildings = $this->service->forLandlord($this->landlord->id)['buildings'];

        return collect($buildings)->firstWhere('building_id', $this->building->id);
    }
}
