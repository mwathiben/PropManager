<?php

namespace Tests\Unit\Services;

use App\Contracts\SmsServiceInterface;
use App\Models\NotificationProviderConfig;
use App\Models\User;
use App\Services\AfricasTalkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AfricasTalkingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AfricasTalkingService $service;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SmsServiceInterface::class);
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    public function test_sends_sms_successfully(): void
    {
        NotificationProviderConfig::factory()
            ->sms()
            ->enabled()
            ->forLandlord($this->landlord)
            ->create();

        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['status' => 'Success', 'messageId' => 'ATXid_123abc'],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->send($this->landlord->id, '254712345678', 'Test message');

        $this->assertTrue($result['success']);
        $this->assertEquals('ATXid_123abc', $result['message_id']);
        $this->assertNull($result['error']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.africastalking.com')
                && $request['to'] === '254712345678'
                && $request['message'] === 'Test message';
        });
    }

    public function test_returns_error_when_credentials_missing(): void
    {
        $result = $this->service->send($this->landlord->id, '254712345678', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertEquals('credentials_missing', $result['error']);
        $this->assertNull($result['message_id']);

        Http::assertNothingSent();
    }

    public function test_handles_api_failure(): void
    {
        NotificationProviderConfig::factory()
            ->sms()
            ->enabled()
            ->forLandlord($this->landlord)
            ->create();

        Http::fake([
            'api.africastalking.com/*' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $result = $this->service->send($this->landlord->id, '254712345678', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('HTTP 500', $result['error']);
    }

    public function test_handles_recipient_failure_status(): void
    {
        NotificationProviderConfig::factory()
            ->sms()
            ->enabled()
            ->forLandlord($this->landlord)
            ->create();

        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['status' => 'InvalidPhoneNumber', 'messageId' => null],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->send($this->landlord->id, '254000000000', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('InvalidPhoneNumber', $result['error']);
    }

    public function test_masks_phone_in_logs(): void
    {
        NotificationProviderConfig::factory()
            ->sms()
            ->enabled()
            ->forLandlord($this->landlord)
            ->create();

        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['status' => 'Success', 'messageId' => 'ATX_mask_test'],
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'SMS sent')
                    && $context['phone'] === '5678'
                    && ! str_contains(json_encode($context), '254712345678');
            });

        $this->service->send($this->landlord->id, '254712345678', 'Test message');
    }

    public function test_returns_message_id_on_success(): void
    {
        NotificationProviderConfig::factory()
            ->sms()
            ->enabled()
            ->forLandlord($this->landlord)
            ->create();

        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['status' => 'Success', 'messageId' => 'ATXid_unique_456'],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->send($this->landlord->id, '254712345678', 'Payment received');

        $this->assertEquals('ATXid_unique_456', $result['message_id']);
    }
}
