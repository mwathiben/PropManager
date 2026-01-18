<?php

namespace Tests\Unit\Repositories;

use App\Models\NotificationProviderConfig;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\DualWriteNotificationConfigRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualWriteNotificationConfigRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DualWriteNotificationConfigRepository $repository;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DualWriteNotificationConfigRepository;
        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($this->landlord);
    }

    public function test_get_sms_provider_returns_none_when_not_configured(): void
    {
        $provider = $this->repository->getSmsProvider($this->landlord->id);

        $this->assertEquals('none', $provider);
    }

    public function test_get_sms_provider_reads_from_setting_when_flag_off(): void
    {
        config(['features.notification_v2' => false]);

        Setting::set('sms_provider', 'twilio', false, 'notifications', null, $this->landlord->id);

        $provider = $this->repository->getSmsProvider($this->landlord->id);

        $this->assertEquals('twilio', $provider);
    }

    public function test_get_sms_provider_reads_from_config_when_flag_on(): void
    {
        config(['features.notification_v2' => true]);

        NotificationProviderConfig::create([
            'landlord_id' => $this->landlord->id,
            'provider_type' => NotificationProviderConfig::TYPE_SMS,
            'provider_name' => 'africas_talking',
            'is_enabled' => true,
        ]);

        $provider = $this->repository->getSmsProvider($this->landlord->id);

        $this->assertEquals('africas_talking', $provider);
    }

    public function test_get_twilio_credentials_returns_empty_array_when_not_configured(): void
    {
        $credentials = $this->repository->getTwilioCredentials($this->landlord->id);

        $this->assertEquals(['account_sid' => null, 'auth_token' => null, 'phone_number' => null], $credentials);
    }

    public function test_get_twilio_credentials_reads_from_setting_when_flag_off(): void
    {
        config(['features.notification_v2' => false]);

        // Note: Using non-encrypted for test simplicity; encryption is handled by Setting model
        Setting::set('twilio_account_sid', 'AC123', false, 'notifications', null, $this->landlord->id);
        Setting::set('twilio_auth_token', 'secret', false, 'notifications', null, $this->landlord->id);
        Setting::set('twilio_phone_number', '+1234567890', false, 'notifications', null, $this->landlord->id);

        $credentials = $this->repository->getTwilioCredentials($this->landlord->id);

        $this->assertEquals('AC123', $credentials['account_sid']);
        $this->assertEquals('secret', $credentials['auth_token']);
        $this->assertEquals('+1234567890', $credentials['phone_number']);
    }

    public function test_get_twilio_credentials_reads_from_config_when_flag_on(): void
    {
        config(['features.notification_v2' => true]);

        NotificationProviderConfig::create([
            'landlord_id' => $this->landlord->id,
            'provider_type' => NotificationProviderConfig::TYPE_SMS,
            'provider_name' => 'twilio',
            'credentials' => [
                'account_sid' => 'AC456',
                'auth_token' => 'token456',
                'phone_number' => '+9876543210',
            ],
            'is_enabled' => true,
        ]);

        $credentials = $this->repository->getTwilioCredentials($this->landlord->id);

        $this->assertEquals('AC456', $credentials['account_sid']);
        $this->assertEquals('token456', $credentials['auth_token']);
        $this->assertEquals('+9876543210', $credentials['phone_number']);
    }

    public function test_set_twilio_credentials_writes_to_both_tables(): void
    {
        $credentials = [
            'account_sid' => 'AC789',
            'auth_token' => 'auth789',
            'phone_number' => '+1112223333',
        ];

        $this->repository->setTwilioCredentials($this->landlord->id, $credentials);

        // Verify Setting table entries exist
        $this->assertDatabaseHas('settings', [
            'landlord_id' => $this->landlord->id,
            'key' => 'twilio_account_sid',
        ]);
        $this->assertDatabaseHas('settings', [
            'landlord_id' => $this->landlord->id,
            'key' => 'twilio_auth_token',
        ]);
        $this->assertEquals('+1112223333', Setting::get('twilio_phone_number', null, $this->landlord->id));

        // Verify NotificationProviderConfig table
        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_SMS);
        $this->assertNotNull($config);
        $this->assertEquals('twilio', $config->provider_name);
        $this->assertEquals('AC789', $config->getCredential('account_sid'));
        $this->assertEquals('auth789', $config->getCredential('auth_token'));
        $this->assertEquals('+1112223333', $config->getCredential('phone_number'));
    }

    public function test_get_africas_talking_credentials_reads_from_setting_when_flag_off(): void
    {
        config(['features.notification_v2' => false]);

        // Note: Using non-encrypted for test simplicity
        Setting::set('africas_talking_api_key', 'api123', false, 'notifications', null, $this->landlord->id);
        Setting::set('africas_talking_username', 'sandbox', false, 'notifications', null, $this->landlord->id);
        Setting::set('africas_talking_from', 'PropManager', false, 'notifications', null, $this->landlord->id);

        $credentials = $this->repository->getAfricasTalkingCredentials($this->landlord->id);

        $this->assertEquals('api123', $credentials['api_key']);
        $this->assertEquals('sandbox', $credentials['username']);
        $this->assertEquals('PropManager', $credentials['from']);
    }

    public function test_set_africas_talking_credentials_writes_to_both_tables(): void
    {
        $credentials = [
            'api_key' => 'key123',
            'username' => 'testuser',
            'from' => 'TestSender',
        ];

        $this->repository->setAfricasTalkingCredentials($this->landlord->id, $credentials);

        // Verify Setting table entries exist
        $this->assertDatabaseHas('settings', [
            'landlord_id' => $this->landlord->id,
            'key' => 'africas_talking_api_key',
        ]);
        $this->assertEquals('testuser', Setting::get('africas_talking_username', null, $this->landlord->id));
        $this->assertEquals('TestSender', Setting::get('africas_talking_from', null, $this->landlord->id));

        // Verify NotificationProviderConfig table
        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_SMS);
        $this->assertNotNull($config);
        $this->assertEquals('africas_talking', $config->provider_name);
        $this->assertEquals('key123', $config->getCredential('api_key'));
    }

    public function test_get_whatsapp_number_reads_from_setting_when_flag_off(): void
    {
        config(['features.notification_v2' => false]);

        Setting::set('twilio_whatsapp_number', '+254712345678', false, 'notifications', null, $this->landlord->id);

        $number = $this->repository->getWhatsAppNumber($this->landlord->id);

        $this->assertEquals('+254712345678', $number);
    }

    public function test_get_whatsapp_number_reads_from_config_when_flag_on(): void
    {
        config(['features.notification_v2' => true]);

        NotificationProviderConfig::create([
            'landlord_id' => $this->landlord->id,
            'provider_type' => NotificationProviderConfig::TYPE_WHATSAPP,
            'provider_name' => 'twilio',
            'credentials' => [
                'whatsapp_number' => '+254787654321',
            ],
            'is_enabled' => true,
        ]);

        $number = $this->repository->getWhatsAppNumber($this->landlord->id);

        $this->assertEquals('+254787654321', $number);
    }

    public function test_get_rate_limits_returns_defaults_when_not_configured(): void
    {
        $limits = $this->repository->getRateLimits($this->landlord->id);

        $this->assertEquals(100, $limits['hourly']);
        $this->assertEquals(1000, $limits['daily']);
    }

    public function test_get_rate_limits_reads_from_setting_when_flag_off(): void
    {
        config(['features.notification_v2' => false]);

        Setting::set('notification_rate_limit_hourly', 50, false, 'notifications', null, $this->landlord->id);
        Setting::set('notification_rate_limit_daily', 500, false, 'notifications', null, $this->landlord->id);

        $limits = $this->repository->getRateLimits($this->landlord->id);

        $this->assertEquals(50, $limits['hourly']);
        $this->assertEquals(500, $limits['daily']);
    }

    public function test_get_whatsapp_template_sid_reads_from_setting_when_flag_off(): void
    {
        config(['features.notification_v2' => false]);

        Setting::set('whatsapp_template_rent_reminder_sid', 'HX123456', false, 'notifications', null, $this->landlord->id);

        $sid = $this->repository->getWhatsAppTemplateSid($this->landlord->id, 'rent_reminder');

        $this->assertEquals('HX123456', $sid);
    }

    public function test_set_whatsapp_template_sid_writes_to_both_tables(): void
    {
        $this->repository->setWhatsAppTemplateSid($this->landlord->id, 'invoice', 'HX789012');

        // Verify Setting table
        $this->assertEquals('HX789012', Setting::get('whatsapp_template_invoice_sid', null, $this->landlord->id));

        // Verify NotificationProviderConfig table
        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_WHATSAPP);
        $this->assertNotNull($config);
        $this->assertEquals('HX789012', $config->getSetting('templates', [])['invoice'] ?? null);
    }
}
