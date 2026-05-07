<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class WaterReadingControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $this->setupData['building']->update([
            'water_billing_type' => 'consumption',
        ]);
    }

    public function test_landlord_can_view_water_readings_form(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('readings.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Readings/Index')
        );
    }

    public function test_caretaker_can_submit_water_readings(): void
    {
        $caretaker = $this->createCaretakerForLandlord($this->landlord, $this->setupData['building']);
        $unit = $this->setupData['units']->first();
        $this->createTenantWithActiveLease($this->landlord, $unit);

        $response = $this->actingAs($caretaker)
            ->post(route('readings.store'), [
                'readings' => [
                    [
                        'unit_id' => $unit->id,
                        'current_reading' => 1025,
                        'reading_date' => now()->toDateString(),
                        'photo' => UploadedFile::fake()->image('meter.jpg'),
                    ],
                ],
            ]);

        $response->assertRedirect();
    }

    public function test_landlord_can_view_readings_history(): void
    {
        $unit = $this->setupData['units']->first();
        $this->createWaterReadingForUnit($unit);

        $response = $this->actingAs($this->landlord)
            ->get(route('readings.history'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Readings/History')
        );
    }

    public function test_landlord_can_filter_history_by_building(): void
    {
        $unit = $this->setupData['units']->first();
        $this->createWaterReadingForUnit($unit);

        $response = $this->actingAs($this->landlord)
            ->get(route('readings.history', ['building_id' => $this->setupData['building']->id]));

        $response->assertOk();
    }

    public function test_landlord_can_approve_pending_reading(): void
    {
        $unit = $this->setupData['units']->first();
        $reading = $this->createWaterReadingForUnit($unit);

        $this->assertEquals(\App\Enums\WaterReadingStatus::Pending, $reading->status);

        $response = $this->actingAs($this->landlord)
            ->post(route('readings.approve', $reading), [
                'notes' => 'Verified reading',
            ]);

        $response->assertRedirect();

        $reading->refresh();
        $this->assertEquals(\App\Enums\WaterReadingStatus::Approved, $reading->status);
    }

    public function test_landlord_can_reject_pending_reading(): void
    {
        $unit = $this->setupData['units']->first();
        $reading = $this->createWaterReadingForUnit($unit);

        $response = $this->actingAs($this->landlord)
            ->post(route('readings.reject', $reading), [
                'reason' => 'Reading appears incorrect',
            ]);

        $response->assertRedirect();

        $reading->refresh();
        $this->assertEquals(\App\Enums\WaterReadingStatus::Rejected, $reading->status);
    }

    public function test_can_update_non_invoiced_reading(): void
    {
        $unit = $this->setupData['units']->first();
        $reading = $this->createWaterReadingForUnit($unit);

        $response = $this->actingAs($this->landlord)
            ->put(route('readings.update', $reading), [
                'current_reading' => 1050,
                'reading_date' => now()->toDateString(),
            ]);

        $response->assertRedirect();
    }

    public function test_can_delete_non_invoiced_reading(): void
    {
        $unit = $this->setupData['units']->first();
        $reading = $this->createWaterReadingForUnit($unit);

        $response = $this->actingAs($this->landlord)
            ->delete(route('readings.destroy', $reading));

        $response->assertRedirect();
        $this->assertDatabaseMissing('water_readings', ['id' => $reading->id]);
    }

    public function test_reading_requires_photo(): void
    {
        $unit = $this->setupData['units']->first();

        $response = $this->actingAs($this->landlord)
            ->post(route('readings.store'), [
                'readings' => [
                    [
                        'unit_id' => $unit->id,
                        'current_reading' => 1025,
                        'reading_date' => now()->toDateString(),
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_tenant_cannot_submit_readings(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $response = $this->actingAs($tenant)
            ->post(route('readings.store'), [
                'readings' => [
                    [
                        'unit_id' => $unit->id,
                        'current_reading' => 1025,
                        'reading_date' => now()->toDateString(),
                        'photo' => UploadedFile::fake()->image('meter.jpg'),
                    ],
                ],
            ]);

        $response->assertForbidden();
    }
}
