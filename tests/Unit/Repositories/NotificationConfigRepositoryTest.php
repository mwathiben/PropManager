<?php

namespace Tests\Unit\Repositories;

use App\Models\NotificationProviderConfig;
use App\Models\User;
use App\Repositories\NotificationConfigRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationConfigRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private NotificationConfigRepository $repository;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new NotificationConfigRepository;
        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($this->landlord);
    }

    public function test_get_sms_provider_returns_none_when_not_configured(): void
    {
        $provider = $this->repository->getSmsProvider($this->landlord->id);

        $this->assertEquals('none', $provider);
    }

    public function test_get_sms_provider_reads_from_config(): void
    {
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

    public function test_get_twilio_credentials_reads_from_config(): void
    {
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

    public function test_set_twilio_credentials_saves_to_config(): void
    {
        $credentials = [
            'account_sid' => 'AC789',
            'auth_token' => 'auth789',
            'phone_number' => '+1112223333',
        ];

        $this->repository->setTwilioCredentials($this->landlord->id, $credentials);

        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_SMS);
        $this->assertNotNull($config);
        $this->assertEquals('twilio', $config->provider_name);
        $this->assertEquals('AC789', $config->getCredential('account_sid'));
        $this->assertEquals('auth789', $config->getCredential('auth_token'));
        $this->assertEquals('+1112223333', $config->getCredential('phone_number'));
    }

    public function test_get_africas_talking_credentials_returns_empty_when_not_configured(): void
    {
        $credentials = $this->repository->getAfricasTalkingCredentials($this->landlord->id);

        $this->assertEquals(['api_key' => null, 'username' => null, 'from' => null], $credentials);
    }

    public function test_set_africas_talking_credentials_saves_to_config(): void
    {
        $credentials = [
            'api_key' => 'key123',
            'username' => 'testuser',
            'from' => 'TestSender',
        ];

        $this->repository->setAfricasTalkingCredentials($this->landlord->id, $credentials);

        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_SMS);
        $this->assertNotNull($config);
        $this->assertEquals('africas_talking', $config->provider_name);
        $this->assertEquals('key123', $config->getCredential('api_key'));
        $this->assertEquals('testuser', $config->getCredential('username'));
        $this->assertEquals('TestSender', $config->getCredential('from'));
    }

    public function test_get_whatsapp_number_returns_null_when_not_configured(): void
    {
        $number = $this->repository->getWhatsAppNumber($this->landlord->id);

        $this->assertNull($number);
    }

    public function test_get_whatsapp_number_reads_from_config(): void
    {
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

    public function test_set_whatsapp_template_sid_saves_to_config(): void
    {
        $this->repository->setWhatsAppTemplateSid($this->landlord->id, 'invoice', 'HX789012');

        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_WHATSAPP);
        $this->assertNotNull($config);
        $this->assertEquals('HX789012', $config->getSetting('templates', [])['invoice'] ?? null);
    }

    public function test_is_provider_configured_returns_false_when_not_set(): void
    {
        $result = $this->repository->isProviderConfigured($this->landlord->id, 'sms');

        $this->assertFalse($result);
    }

    public function test_is_provider_configured_returns_true_when_configured(): void
    {
        NotificationProviderConfig::create([
            'landlord_id' => $this->landlord->id,
            'provider_type' => NotificationProviderConfig::TYPE_SMS,
            'provider_name' => 'twilio',
            'credentials' => [
                'account_sid' => 'AC123',
                'auth_token' => 'token',
                'phone_number' => '+1234567890',
            ],
            'is_enabled' => true,
        ]);

        $result = $this->repository->isProviderConfigured($this->landlord->id, 'sms');

        $this->assertTrue($result);
    }

    // ==========================================================================
    // Partial Update Tests
    // ==========================================================================

    public function test_set_email_credentials_partial_update_preserves_existing(): void
    {
        // Set initial full credentials
        $this->repository->setEmailCredentials($this->landlord->id, [
            'mailer' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => '587',
            'username' => 'user@example.com',
            'password' => 'oldpassword',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'PropManager',
        ]);

        // Partial update - only change password
        $this->repository->setEmailCredentials($this->landlord->id, [
            'password' => 'newpassword',
        ]);

        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_EMAIL);
        $this->assertEquals('smtp.example.com', $config->getCredential('host'));
        $this->assertEquals('587', $config->getCredential('port'));
        $this->assertEquals('user@example.com', $config->getCredential('username'));
        $this->assertEquals('newpassword', $config->getCredential('password'));
        $this->assertEquals('tls', $config->getCredential('encryption'));
    }

    public function test_set_twilio_credentials_partial_update_preserves_existing(): void
    {
        // Set initial full credentials
        $this->repository->setTwilioCredentials($this->landlord->id, [
            'account_sid' => 'AC123',
            'auth_token' => 'token123',
            'phone_number' => '+1234567890',
        ]);

        // Partial update - only change phone_number
        $this->repository->setTwilioCredentials($this->landlord->id, [
            'phone_number' => '+9876543210',
        ]);

        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_SMS);
        $this->assertEquals('AC123', $config->getCredential('account_sid'));
        $this->assertEquals('token123', $config->getCredential('auth_token'));
        $this->assertEquals('+9876543210', $config->getCredential('phone_number'));
    }

    public function test_set_africas_talking_credentials_partial_update_preserves_existing(): void
    {
        // Set initial full credentials
        $this->repository->setAfricasTalkingCredentials($this->landlord->id, [
            'api_key' => 'key123',
            'username' => 'sandbox',
            'from' => 'PropManager',
        ]);

        // Partial update - only change from
        $this->repository->setAfricasTalkingCredentials($this->landlord->id, [
            'from' => 'NewSender',
        ]);

        $config = NotificationProviderConfig::forLandlord($this->landlord->id, NotificationProviderConfig::TYPE_SMS);
        $this->assertEquals('key123', $config->getCredential('api_key'));
        $this->assertEquals('sandbox', $config->getCredential('username'));
        $this->assertEquals('NewSender', $config->getCredential('from'));
    }

    public function test_get_email_credentials_returns_defaults_when_not_configured(): void
    {
        $credentials = $this->repository->getEmailCredentials($this->landlord->id);

        $this->assertNull($credentials['mailer']);
        $this->assertNull($credentials['host']);
        $this->assertTrue($credentials['enabled']);
    }

    public function test_is_email_enabled_returns_true_by_default(): void
    {
        $enabled = $this->repository->isEmailEnabled($this->landlord->id);

        $this->assertTrue($enabled);
    }

    public function test_is_setup_complete_returns_false_when_not_marked(): void
    {
        $complete = $this->repository->isSetupComplete($this->landlord->id);

        $this->assertFalse($complete);
    }

    public function test_mark_setup_complete_sets_flag(): void
    {
        $this->repository->markSetupComplete($this->landlord->id);

        $this->assertTrue($this->repository->isSetupComplete($this->landlord->id));
    }
}
