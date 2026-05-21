<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-79 DASHBOARD-WATER: water-reading review is a Water-hub concern. The
 * landlord dashboard no longer surfaces/computes it; the caretaker dashboard
 * water widgets gate on the charges-for-water module rule.
 */
class Phase79DashboardWaterTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function enableWater(): void
    {
        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);
    }

    public function test_landlord_dashboard_omits_pending_readings_action_item(): void
    {
        $data = app(DashboardService::class)->getLandlordDashboardData($this->landlord->fresh(), Request::create('/dashboard'));

        $this->assertArrayNotHasKey('pending_readings', $data['actionItems']);
    }

    public function test_caretaker_water_widget_off_when_landlord_not_charging(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $data = app(DashboardService::class)->getCaretakerDashboardData($caretaker->fresh());

        $this->assertFalse($data['hasWaterEnabled']);
    }

    public function test_caretaker_water_widget_on_when_landlord_charges(): void
    {
        $this->enableWater();
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $data = app(DashboardService::class)->getCaretakerDashboardData($caretaker->fresh());

        $this->assertTrue($data['hasWaterEnabled']);
    }
}
