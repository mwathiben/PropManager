<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\AlertFiring;
use App\Models\AuditLog;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-67 INBOX-OBSERVABILITY CI: the inbox:depth-rollup gauges, the
 * 24h infection window, the read-ratio math, and the
 * inbox_attachment_infected sev2 alert (fire + resolve + resolvable
 * runbook anchor).
 */
class Phase67InboxObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private function recordInfection(User $sender, ?\Illuminate\Support\Carbon $when = null): AuditLog
    {
        $this->actingAs($sender);
        $row = AuditLog::record('inbox.attachment.infected', $sender, [
            'metadata' => ['file_name' => 'evil.pdf', 'signature' => 'Eicar-Test-Signature'],
        ]);

        if ($when !== null) {
            $row->forceFill(['created_at' => $when])->saveQuietly();
        }

        return $row;
    }

    public function test_rollup_emits_all_health_gauges(): void
    {
        $spy = $this->spy(MetricsService::class);

        $this->artisan('inbox:depth-rollup')->assertSuccessful();

        foreach ([
            'inbox_threads_total',
            'inbox_threads_open',
            'inbox_read_ratio',
            'inbox_messages_24h',
            'inbox_attachment_scans_24h',
            'inbox_attachment_infected_24h',
        ] as $gauge) {
            $spy->shouldHaveReceived('gauge')->withArgs(fn (string $name) => $name === $gauge);
        }
    }

    public function test_read_ratio_is_caught_up_over_total_participants(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $thread = MessageThread::create(['landlord_id' => $landlord->id, 'title' => 'T']);
        $thread->messages()->create(['sender_id' => $tenant->id, 'body' => 'hi']);
        $thread->refresh();

        // Landlord caught up (read after the last message); tenant never read.
        $thread->participants()->attach($landlord->id, [
            'role' => 'landlord',
            'last_read_at' => $thread->last_message_at->copy()->addMinute(),
        ]);
        $thread->participants()->attach($tenant->id, ['role' => 'tenant', 'last_read_at' => null]);

        $spy = $this->spy(MetricsService::class);
        $this->artisan('inbox:depth-rollup')->assertSuccessful();

        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name, float $value) => $name === 'inbox_read_ratio' && abs($value - 0.5) < 0.0001,
        );
    }

    public function test_read_ratio_is_one_when_no_participants(): void
    {
        $spy = $this->spy(MetricsService::class);
        $this->artisan('inbox:depth-rollup')->assertSuccessful();

        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name, float $value) => $name === 'inbox_read_ratio' && abs($value - 1.0) < 0.0001,
        );
    }

    public function test_infected_gauge_counts_only_within_24h_window(): void
    {
        $sender = User::factory()->create(['role' => 'landlord']);
        $this->recordInfection($sender);                          // in window
        $this->recordInfection($sender, now()->subHours(25));     // out of window

        $spy = $this->spy(MetricsService::class);
        $this->artisan('inbox:depth-rollup')->assertSuccessful();

        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name, float $value) => $name === 'inbox_attachment_infected_24h' && abs($value - 1.0) < 0.0001,
        );
    }

    public function test_rollup_fires_sev2_alert_on_infection_in_window(): void
    {
        $this->recordInfection(User::factory()->create(['role' => 'landlord']));

        $this->artisan('inbox:depth-rollup')->assertSuccessful();

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'inbox_attachment_infected',
            'severity' => 'sev2',
            'resolved_at' => null,
        ]);
    }

    public function test_rollup_resolves_alert_when_no_infection(): void
    {
        AlertFiring::create([
            'alert_key' => 'inbox_attachment_infected',
            'severity' => 'sev2',
            'value' => 3,
            'threshold' => 0,
            'fired_at' => now()->subHour(),
        ]);

        $this->artisan('inbox:depth-rollup')->assertSuccessful();

        $firing = AlertFiring::where('alert_key', 'inbox_attachment_infected')->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_infected_alert_is_registered_with_resolvable_runbook(): void
    {
        $alert = collect(config('alerts.alerts'))
            ->firstWhere('key', 'inbox_attachment_infected');

        $this->assertNotNull($alert, 'inbox_attachment_infected must be registered in config/alerts.php');
        $this->assertSame('sev2', $alert['severity']);
        $this->assertSame('inbox_attachment_infected_24h', $alert['gauge']);

        // The runbook reference (file#anchor) must resolve — same gate as
        // runbook:coverage-audit (Phase-32 SRE-RUNBOOK-2).
        [$file, $anchor] = array_pad(explode('#', $alert['runbook'], 2), 2, null);
        $this->assertFileExists(base_path($file));
        $this->assertNotNull($anchor);

        $markdown = (string) file_get_contents(base_path($file));
        $headings = [];
        foreach (preg_split('/\R/', $markdown) as $line) {
            if (preg_match('/^#{1,6}\s+(.*)$/', $line, $m)) {
                $slug = strtolower(trim($m[1]));
                $slug = preg_replace('/[^a-z0-9 -]/', '', $slug);
                $headings[] = preg_replace('/\s+/', '-', $slug);
            }
        }
        $this->assertContains($anchor, $headings, "Runbook anchor #{$anchor} not found in {$file}");
    }
}
