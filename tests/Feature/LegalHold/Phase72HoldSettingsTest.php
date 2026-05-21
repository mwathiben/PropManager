<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Mail\StaleHoldReminderMailable;
use App\Models\LandlordHoldSettings;
use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Legal\HoldSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-72 HOLD-SETTINGS: the resolver (override vs config fallback), the
 * settings update + validation bounds, and the sweeper honouring a landlord's
 * per-landlord stale window.
 */
class Phase72HoldSettingsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    public function test_resolver_uses_the_landlord_override(): void
    {
        LandlordHoldSettings::create([
            'landlord_id' => $this->landlord->id,
            'stale_after_days' => 45,
            'reminder_cooldown_days' => 7,
        ]);

        $resolver = app(HoldSettingsResolver::class);
        $this->assertSame(45, $resolver->staleAfterDays($this->landlord->id));
        $this->assertSame(7, $resolver->reminderCooldownDays($this->landlord->id));
    }

    public function test_resolver_falls_back_to_config(): void
    {
        $resolver = app(HoldSettingsResolver::class);
        $this->assertSame((int) config('legal_hold.stale_after_days'), $resolver->staleAfterDays($this->landlord->id));
        $this->assertSame((int) config('legal_hold.stale_reminder_cooldown_days'), $resolver->reminderCooldownDays($this->landlord->id));
    }

    public function test_update_persists_settings(): void
    {
        $this->actingAs($this->landlord)
            ->put(route('legal-holds.settings.update'), [
                'stale_after_days' => 90,
                'reminder_cooldown_days' => 14,
                'reminder_recipients' => ['legal@example.com'],
                'auto_hold_on_eviction' => true,
            ])
            ->assertRedirect(route('legal-holds.settings'));

        $row = LandlordHoldSettings::where('landlord_id', $this->landlord->id)->first();
        $this->assertSame(90, $row->stale_after_days);
        $this->assertSame(['legal@example.com'], $row->reminder_recipients);
        $this->assertTrue($row->auto_hold_on_eviction);
    }

    public function test_update_rejects_out_of_bounds(): void
    {
        $this->actingAs($this->landlord)
            ->put(route('legal-holds.settings.update'), ['stale_after_days' => 10])
            ->assertSessionHasErrors('stale_after_days');

        $this->actingAs($this->landlord)
            ->put(route('legal-holds.settings.update'), ['reminder_recipients' => ['not-an-email']])
            ->assertSessionHasErrors('reminder_recipients.0');
    }

    public function test_sweeper_honours_the_per_landlord_window(): void
    {
        Mail::fake();

        $thread = MessageThread::create(['landlord_id' => $this->landlord->id]);
        $hold = LegalHold::create([
            'holdable_type' => MessageThread::class,
            'holdable_id' => $thread->id,
            'reason' => 'Held for a while.',
            'held_by' => $this->landlord->id,
            'held_at' => now()->subDays(60),
        ]);

        // 60 days < the 365-day default — not stale yet without an override.
        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();
        $this->assertNull($hold->fresh()->last_reminded_at);

        // A 30-day override makes the same hold stale -> reminded.
        LandlordHoldSettings::create(['landlord_id' => $this->landlord->id, 'stale_after_days' => 30]);
        $this->artisan('legal-hold:sweep-stale')->assertSuccessful();

        $this->assertNotNull($hold->fresh()->last_reminded_at);
        Mail::assertQueued(StaleHoldReminderMailable::class);
    }
}
