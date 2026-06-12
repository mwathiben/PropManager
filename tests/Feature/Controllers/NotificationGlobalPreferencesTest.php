<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * M2 decomposition safety net: characterizes the global notification
 * preferences endpoints (getGlobalPreferences / updateGlobalPreferences)
 * BEFORE that logic is extracted out of NotificationsController into
 * NotificationSettingsService. These had no route-level coverage; the
 * controller actions stay (routes unchanged) and delegate to the service.
 */
class NotificationGlobalPreferencesTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_get_global_preferences_returns_expected_structure(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->getJson(route('notifications.settings.global.get'))
            ->assertOk()
            ->assertJsonStructure([
                'preferences' => [
                    'quiet_hours_enabled',
                    'quiet_hours_start',
                    'quiet_hours_end',
                    'notification_max_retries',
                    'default_notification_channels',
                ],
            ]);
    }

    public function test_update_global_preferences_persists_and_round_trips(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->post(route('notifications.settings.global'), [
                'quiet_hours_enabled' => true,
                'quiet_hours_start' => '23:00',
                'quiet_hours_end' => '07:00',
                'notification_max_retries' => 4,
                'notification_daily_limit_per_tenant' => 15,
                'default_notification_channels' => ['email', 'sms'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($landlord)
            ->getJson(route('notifications.settings.global.get'))
            ->assertOk()
            ->assertJsonPath('preferences.quiet_hours_enabled', true)
            ->assertJsonPath('preferences.notification_max_retries', 4);
    }
}
