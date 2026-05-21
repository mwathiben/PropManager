<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-79 WATER-ROLES: the hub is role-aware — caretaker RECORDS, landlord
 * REVIEWS; caretaker can never approve/reject.
 */
class Phase79WaterRolesTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];

        // Enable the water module: plan permits + landlord charges.
        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);
    }

    private function caretaker(): User
    {
        return Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));
    }

    private function pendingReading(): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->pending()->create([
            'unit_id' => $this->units->get(0)->id,
            'landlord_id' => $this->landlord->id,
        ]));
    }

    public function test_landlord_hub_shows_review_not_input(): void
    {
        $response = $this->actingAs($this->landlord->fresh())->get(route('water.hub'));
        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('landlord', $props['role']);
        $this->assertTrue($props['canReview']);
        $this->assertFalse($props['canInput']);
        $this->assertSame('review', $props['activeTab']);
    }

    public function test_caretaker_hub_shows_input_not_review(): void
    {
        $response = $this->actingAs($this->caretaker()->fresh())->get(route('water.hub'));
        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('caretaker', $props['role']);
        $this->assertTrue($props['canInput']);
        $this->assertFalse($props['canReview']);
        $this->assertSame('readings', $props['activeTab']);
    }

    public function test_landlord_review_tab_request_is_honoured(): void
    {
        // A landlord cannot force the input tab.
        $response = $this->actingAs($this->landlord->fresh())->get(route('water.hub', ['tab' => 'readings']));
        $response->assertOk();
        $this->assertSame('review', $response->viewData('page')['props']['activeTab']);
    }

    public function test_caretaker_cannot_force_the_review_tab(): void
    {
        $response = $this->actingAs($this->caretaker()->fresh())->get(route('water.hub', ['tab' => 'review']));
        $response->assertOk();
        $this->assertSame('readings', $response->viewData('page')['props']['activeTab']);
    }

    public function test_caretaker_cannot_approve(): void
    {
        $reading = $this->pendingReading();

        $this->actingAs($this->caretaker()->fresh())
            ->post(route('readings.approve', $reading->id))
            ->assertForbidden();

        $this->assertSame('pending', $reading->fresh()->status->value);
    }

    public function test_caretaker_cannot_reject(): void
    {
        $reading = $this->pendingReading();

        $this->actingAs($this->caretaker()->fresh())
            ->post(route('readings.reject', $reading->id), ['reason' => 'bad'])
            ->assertForbidden();

        $this->assertSame('pending', $reading->fresh()->status->value);
    }

    public function test_landlord_can_approve(): void
    {
        $reading = $this->pendingReading();

        $this->actingAs($this->landlord->fresh())
            ->post(route('readings.approve', $reading->id), ['notes' => 'ok'])
            ->assertRedirect();

        $this->assertSame('approved', $reading->fresh()->status->value);
    }
}
