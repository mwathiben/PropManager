<?php

declare(strict_types=1);

namespace Tests\Feature\Vendors;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-39 VENDOR-OBSERV-1/2: push:click-through-audit cron +
 * vendor_flap alert config + analytics_forwarder_error_rate gauge.
 */
class Phase39VendorObservTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $recipient, ?\DateTimeInterface $sentAt = null, ?\DateTimeInterface $readAt = null): void
    {
        Notification::query()->withoutGlobalScopes()->create([
            'landlord_id' => $recipient->id,
            'recipient_id' => $recipient->id,
            'type' => 'general',
            'channel' => 'push',
            'subject' => 'x',
            'message' => 'y',
            'data' => null,
            'status' => 'sent',
            'sent_at' => $sentAt ?? now()->subHours(2),
            'read_at' => $readAt,
        ]);
    }

    public function test_click_through_audit_reports_zero_when_no_pushes_sent(): void
    {
        $this->artisan('push:click-through-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('0 sent, 0 clicked, rate=0');
    }

    public function test_click_through_audit_reports_correct_rate(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->notify($user, sentAt: now()->subHours(2), readAt: $i < 3 ? now()->subHour() : null);
        }

        $this->artisan('push:click-through-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('10 sent, 3 clicked, rate=0.3');
    }

    public function test_click_through_audit_excludes_older_than_24h(): void
    {
        $user = User::factory()->create();
        // sent yesterday morning (>24h ago)
        $this->notify($user, sentAt: now()->subDays(2), readAt: now()->subDay());
        // sent recently
        $this->notify($user, sentAt: now()->subHours(3), readAt: now()->subHour());

        $this->artisan('push:click-through-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('1 sent, 1 clicked');
    }

    public function test_vendor_flap_alert_is_registered_in_config(): void
    {
        $alerts = collect(config('alerts.alerts'))->keyBy('key');
        $this->assertTrue($alerts->has('vendor_flap'));
        $this->assertSame('sev4', $alerts->get('vendor_flap')['severity']);
        $this->assertSame('analytics_forwarder_error_rate', $alerts->get('vendor_flap')['gauge']);
    }

    public function test_click_through_audit_command_is_scheduled_daily(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $hit = collect($schedule->events())
            ->contains(fn ($event) => str_contains($event->command ?? '', 'push:click-through-audit'));
        $this->assertTrue($hit, 'push:click-through-audit should be scheduled');
    }
}
