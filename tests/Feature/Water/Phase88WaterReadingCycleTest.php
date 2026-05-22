<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Enums\WaterReadingStatus;
use App\Models\Notification;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\Water\WaterModuleAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-88 WATER-READING-CYCLE: reading reminder, review window, the auto-approve
 * safety, and re-read.
 */
class Phase88WaterReadingCycleTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    private $building;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-06-15 10:00:00');

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];
        $this->building = $setup['building'];

        $this->caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));
        $this->building->update(['water_billing_type' => 'consumption', 'caretaker_id' => $this->caretaker->id]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pendingReading(int $unitIndex, ?Carbon $recordedAt = null): WaterReading
    {
        $unit = $this->units->get($unitIndex);
        $reading = Model::withoutEvents(fn () => WaterReading::create([
            'unit_id' => $unit->id,
            'landlord_id' => $this->landlord->id,
            'previous_reading' => 0,
            'current_reading' => 10,
            'consumption' => 10,
            'cost' => 1500,
            'reading_date' => now()->toDateString(),
            'status' => 'pending',
            'is_invoiced' => false,
        ]));

        if ($recordedAt) {
            DB::table('water_readings')->where('id', $reading->id)->update(['created_at' => $recordedAt]);
        }

        return $reading->fresh();
    }

    // --- CONFIG ----------------------------------------------------------

    public function test_landlord_persists_reading_cycle_config(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->put(route('water.settings.update'), [
                'water_billing_type' => 'consumption',
                'water_unit_rate' => 150,
                'water_reading_day' => 25,
                'water_review_days' => 5,
            ])
            ->assertRedirect();

        $config = PaymentConfiguration::where('landlord_id', $this->landlord->id)->firstOrFail();
        $this->assertSame(25, $config->water_reading_day);
        $this->assertSame(5, $config->water_review_days);
    }

    // --- READING REMINDER ------------------------------------------------

    public function test_caretaker_reminded_on_reading_day(): void
    {
        $this->building->update(['water_reading_day' => 15]); // today is the 15th

        Artisan::call('water:reading-reminders');

        $this->assertTrue(
            Notification::where('recipient_id', $this->caretaker->id)
                ->where('type', Notification::TYPE_WATER_READING_DUE)
                ->exists()
        );
    }

    public function test_no_reminder_when_not_reading_day(): void
    {
        $this->building->update(['water_reading_day' => 3]); // today is the 15th

        Artisan::call('water:reading-reminders');

        $this->assertFalse(
            Notification::where('recipient_id', $this->caretaker->id)
                ->where('type', Notification::TYPE_WATER_READING_DUE)
                ->exists()
        );
    }

    public function test_reading_reminder_is_idempotent_per_month(): void
    {
        $this->building->update(['water_reading_day' => 15]);

        Artisan::call('water:reading-reminders');
        Artisan::call('water:reading-reminders');

        $this->assertSame(
            1,
            Notification::where('recipient_id', $this->caretaker->id)
                ->where('type', Notification::TYPE_WATER_READING_DUE)
                ->count()
        );
    }

    // --- REVIEW WINDOW + AUTO-APPROVE (the safety) -----------------------

    public function test_pending_reading_past_window_is_auto_approved(): void
    {
        $this->building->update(['water_review_days' => 5]);
        // Recorded 10 days ago — past the 5-day window.
        $reading = $this->pendingReading(0, now()->copy()->subDays(10));

        Artisan::call('water:review-window');

        $fresh = $reading->fresh();
        $this->assertSame(WaterReadingStatus::Approved, $fresh->status);
        $this->assertTrue($fresh->auto_approved);
        // Landlord is told it auto-approved.
        $this->assertTrue(
            Notification::where('recipient_id', $this->landlord->id)
                ->where('type', Notification::TYPE_WATER_REVIEW_DUE)
                ->exists()
        );
    }

    public function test_fresh_pending_reading_within_window_is_left_alone(): void
    {
        $this->building->update(['water_review_days' => 5]);
        // Recorded today — well within the window.
        $reading = $this->pendingReading(1, now());

        Artisan::call('water:review-window');

        $this->assertSame(WaterReadingStatus::Pending, $reading->fresh()->status);
    }

    // --- RE-READ ----------------------------------------------------------

    public function test_landlord_can_request_a_reread(): void
    {
        $reading = Model::withoutEvents(fn () => WaterReading::create([
            'unit_id' => $this->units->get(2)->id,
            'landlord_id' => $this->landlord->id,
            'previous_reading' => 0,
            'current_reading' => 10,
            'consumption' => 10,
            'cost' => 1500,
            'reading_date' => now()->toDateString(),
            'status' => 'approved',
            'is_invoiced' => false,
        ]));

        $this->actingAs($this->landlord->fresh())
            ->post(route('readings.request-reread', $reading->id))
            ->assertRedirect();

        $this->assertSame(WaterReadingStatus::Pending, $reading->fresh()->status);
        $this->assertTrue(
            Notification::where('recipient_id', $this->caretaker->id)
                ->where('type', Notification::TYPE_WATER_READING_DUE)
                ->exists()
        );
    }

    public function test_cannot_reread_an_invoiced_reading(): void
    {
        $reading = Model::withoutEvents(fn () => WaterReading::create([
            'unit_id' => $this->units->get(3)->id,
            'landlord_id' => $this->landlord->id,
            'previous_reading' => 0,
            'current_reading' => 10,
            'consumption' => 10,
            'cost' => 1500,
            'reading_date' => now()->toDateString(),
            'status' => 'approved',
            'is_invoiced' => true,
        ]));

        $this->actingAs($this->landlord->fresh())
            ->post(route('readings.request-reread', $reading->id))
            ->assertRedirect();

        $this->assertSame(WaterReadingStatus::Approved, $reading->fresh()->status);
    }
}
