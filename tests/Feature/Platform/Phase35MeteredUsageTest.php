<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AlertFiring;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Platform\MeteredUsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase35MeteredUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_writes_usage_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        app(MeteredUsageRecorder::class)->record($landlord->id, 'properties', 1);

        $this->assertDatabaseHas('usage_records', [
            'user_id' => $landlord->id,
            'feature' => 'properties',
            'quantity' => 1,
        ]);
    }

    public function test_recorder_increments_existing_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(MeteredUsageRecorder::class);
        $recorder->record($landlord->id, 'units', 1);
        $recorder->record($landlord->id, 'units', 1);
        $recorder->record($landlord->id, 'units', 1);

        $row = UsageRecord::where('user_id', $landlord->id)
            ->where('feature', 'units')->first();
        $this->assertSame(3, $row->quantity);
    }

    public function test_recorder_silently_skips_zero_or_negative_delta(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(MeteredUsageRecorder::class);
        $recorder->record($landlord->id, 'units', 0);
        $recorder->record($landlord->id, 'units', -5);

        $this->assertSame(0, UsageRecord::where('user_id', $landlord->id)->count());
    }

    public function test_audit_emits_gauges_for_paying_landlords(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();
        // Plan has max_properties=1; landlord at 1 property = ratio 1.0.
        UsageRecord::setUsage($landlord->id, 'properties', 1);

        $exit = \Artisan::call('metered:soft-cap-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Audited', $output);
    }

    public function test_audit_fires_alert_when_paying_landlord_exceeds_threshold(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();
        // Plan has max_properties=1; record 2 = ratio 2.0 > 1.5 threshold.
        UsageRecord::setUsage($landlord->id, 'properties', 2);

        \Artisan::call('metered:soft-cap-audit');

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'high_metered_overage',
            'severity' => 'sev4',
        ]);
    }

    public function test_audit_does_not_fire_for_free_tier_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        UsageRecord::setUsage($landlord->id, 'properties', 100);

        \Artisan::call('metered:soft-cap-audit');

        $this->assertDatabaseMissing('alert_firings', [
            'alert_key' => 'high_metered_overage',
        ]);
    }

    public function test_audit_resolves_alert_when_no_offenders(): void
    {
        AlertFiring::create([
            'alert_key' => 'high_metered_overage',
            'severity' => 'sev4',
            'value' => 2.5,
            'threshold' => 1.5,
            'fired_at' => now()->subHour(),
        ]);

        \Artisan::call('metered:soft-cap-audit');

        $firing = AlertFiring::where('alert_key', 'high_metered_overage')
            ->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_audit_skips_when_no_paying_landlords(): void
    {
        $exit = \Artisan::call('metered:soft-cap-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No paying landlords', $output);
    }
}
