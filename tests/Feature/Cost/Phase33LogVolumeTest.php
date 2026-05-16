<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Models\AlertFiring;
use App\Models\LogVolumeDaily;
use App\Models\User;
use App\Services\Cost\LandlordLogVolumeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase33LogVolumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_creates_first_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        app(LandlordLogVolumeRecorder::class)->add($landlord->id, 1024, 5);

        $this->assertDatabaseHas('log_volume_daily', [
            'landlord_id' => $landlord->id,
            'byte_count' => 1024,
            'line_count' => 5,
        ]);
    }

    public function test_recorder_atomically_accumulates(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(LandlordLogVolumeRecorder::class);

        $recorder->add($landlord->id, 500, 1);
        $recorder->add($landlord->id, 300, 2);
        $recorder->add($landlord->id, 200, 3);

        $row = LogVolumeDaily::withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)->first();
        $this->assertSame(1000, $row->byte_count);
        $this->assertSame(6, $row->line_count);
        $this->assertSame(1, LogVolumeDaily::withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)->count());
    }

    public function test_audit_emits_distribution_gauges(): void
    {
        $landlords = User::factory()->count(3)->create(['role' => 'landlord']);
        $recorder = app(LandlordLogVolumeRecorder::class);
        $recorder->add($landlords[0]->id, 1_000, 1);
        $recorder->add($landlords[1]->id, 2_000, 1);
        $recorder->add($landlords[2]->id, 3_000, 1);

        $exit = \Artisan::call('log:volume-audit');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('median=2000B', $output);
        $this->assertStringContainsString('Audited 3', $output);
    }

    public function test_audit_fires_alert_when_one_landlord_dominates(): void
    {
        $a = User::factory()->create(['role' => 'landlord']);
        $b = User::factory()->create(['role' => 'landlord']);
        $c = User::factory()->create(['role' => 'landlord']);
        $recorder = app(LandlordLogVolumeRecorder::class);
        $recorder->add($a->id, 100, 1);
        $recorder->add($b->id, 100, 1);
        $recorder->add($c->id, 100_000, 1);

        \Artisan::call('log:volume-audit');

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'high_landlord_log_volume',
            'severity' => 'sev4',
        ]);
    }

    public function test_audit_resolves_alert_when_distribution_recovers(): void
    {
        AlertFiring::create([
            'alert_key' => 'high_landlord_log_volume',
            'severity' => 'sev4',
            'value' => 100000,
            'threshold' => 500,
            'fired_at' => now()->subHour(),
        ]);

        $a = User::factory()->create(['role' => 'landlord']);
        $b = User::factory()->create(['role' => 'landlord']);
        $recorder = app(LandlordLogVolumeRecorder::class);
        $recorder->add($a->id, 1000, 1);
        $recorder->add($b->id, 1200, 1);

        \Artisan::call('log:volume-audit');

        $firing = AlertFiring::where('alert_key', 'high_landlord_log_volume')
            ->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_audit_handles_empty_table(): void
    {
        $exit = \Artisan::call('log:volume-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No log volume rows', $output);
    }
}
