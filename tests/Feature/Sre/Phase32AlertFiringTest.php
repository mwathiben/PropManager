<?php

declare(strict_types=1);

namespace Tests\Feature\Sre;

use App\Events\AlertFiringRecorded;
use App\Models\AlertFiring;
use App\Models\User;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class Phase32AlertFiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_inserts_new_open_firing_and_fires_event(): void
    {
        Event::fake([AlertFiringRecorded::class]);

        $row = app(AlertFiringRecorder::class)->record('failed_jobs_growth', 30, null, ['source' => 'test']);

        $this->assertSame('failed_jobs_growth', $row->alert_key);
        $this->assertSame('sev3', $row->severity);
        $this->assertSame(30.0, $row->value);
        $this->assertSame(25.0, $row->threshold);
        $this->assertNull($row->resolved_at);
        Event::assertDispatchedTimes(AlertFiringRecorded::class, 1);
    }

    public function test_record_updates_existing_open_firing_without_duplicating(): void
    {
        Event::fake([AlertFiringRecorded::class]);
        $recorder = app(AlertFiringRecorder::class);

        $first = $recorder->record('failed_jobs_growth', 30);
        $second = $recorder->record('failed_jobs_growth', 35);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(35.0, $second->value);
        Event::assertDispatchedTimes(AlertFiringRecorded::class, 1);
        $this->assertSame(1, AlertFiring::query()->where('alert_key', 'failed_jobs_growth')->count());
    }

    public function test_resolve_marks_open_firing_as_resolved(): void
    {
        $recorder = app(AlertFiringRecorder::class);
        $row = $recorder->record('failed_jobs_growth', 30);

        $resolved = $recorder->resolve('failed_jobs_growth');

        $this->assertNotNull($resolved);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame($row->id, $resolved->id);
    }

    public function test_resolve_returns_null_when_no_open_firing(): void
    {
        $this->assertNull(app(AlertFiringRecorder::class)->resolve('failed_jobs_growth'));
    }

    public function test_acknowledge_records_user_and_note(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $recorder = app(AlertFiringRecorder::class);
        $recorder->record('failed_jobs_growth', 30);

        $ack = $recorder->acknowledge('failed_jobs_growth', $user->id, 'investigating root cause');

        $this->assertSame($user->id, $ack->acknowledged_by_user_id);
        $this->assertSame('investigating root cause', $ack->acknowledgement_note);
        $this->assertNotNull($ack->acknowledged_at);
    }

    public function test_quality_audit_scores_acknowledged_as_signal(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $recorder = app(AlertFiringRecorder::class);

        $recorder->record('failed_jobs_growth', 30);
        $recorder->acknowledge('failed_jobs_growth', $user->id, 'real issue');
        $recorder->resolve('failed_jobs_growth');

        $this->artisan('alert:quality')
            ->expectsOutputToContain('failed_jobs_growth')
            ->expectsOutputToContain('Alerts in fatigue territory: 0')
            ->assertSuccessful();
    }

    public function test_quality_audit_scores_quick_unacked_resolve_as_noise(): void
    {
        AlertFiring::create([
            'alert_key' => 'queue_depth_high',
            'severity' => 'sev2',
            'value' => 1100,
            'threshold' => 1000,
            'fired_at' => now()->subMinutes(10),
            'resolved_at' => now()->subMinutes(8),
        ]);

        $exit = \Artisan::call('alert:quality');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('queue_depth_high', $output);
        $this->assertStringContainsString('noise=1', $output);
        $this->assertStringContainsString('Alerts in fatigue territory: 1', $output);
    }
}
