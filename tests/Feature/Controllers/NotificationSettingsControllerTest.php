<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * M2 decomposition safety net: characterizes the provider-settings actions
 * (updateProviderSettings / testProvider / checkSetupStatus) BEFORE the
 * settings logic is extracted out of the 1185-line NotificationsController
 * into NotificationSettingsService. These actions had no direct route-level
 * coverage, so this locks the end-to-end behaviour through the real HTTP
 * stack first.
 */
class NotificationSettingsControllerTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_update_sms_provider_then_status_reflects_it(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->post(route('notifications.settings.provider', 'sms'), [
                'sms_provider' => 'twilio',
                'twilio_account_sid' => 'AC_test_sid',
                'twilio_auth_token' => 'test_token',
                'twilio_phone_number' => '+15551234567',
            ])
            ->assertRedirect();

        $status = $this->actingAs($landlord)
            ->getJson(route('notifications.settings.status'));

        $status->assertOk()
            ->assertJsonStructure(['complete', 'providers' => ['email', 'sms', 'whatsapp', 'push']])
            ->assertJsonPath('providers.sms', true);
    }

    public function test_update_email_provider_redirects_with_success(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->post(route('notifications.settings.provider', 'email'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.example.test',
                'mail_port' => '587',
                'mail_from_address' => 'noreply@example.test',
                'mail_from_name' => 'Example',
                'enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_test_sms_provider_returns_result_shape(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->postJson(route('notifications.settings.test', 'sms'))
            ->assertOk()
            ->assertJsonStructure(['success', 'message']);
    }
}
