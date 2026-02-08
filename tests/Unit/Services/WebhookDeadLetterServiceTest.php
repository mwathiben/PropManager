<?php

namespace Tests\Unit\Services;

use App\Mail\FailedWebhookAlert;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use App\Services\Payment\WebhookDeadLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebhookDeadLetterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookDeadLetterService $service;

    protected User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WebhookDeadLetterService::class);
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    public function test_capture_creates_dead_letter_entry(): void
    {
        Mail::fake();

        $result = $this->service->capture(
            WebhookDeadLetter::PROVIDER_MPESA,
            'stk_callback',
            ['amount' => 5000, 'reference' => 'TXN123'],
            'Invoice not found',
            WebhookDeadLetter::ERROR_TRANSIENT,
            $this->landlord->id
        );

        $this->assertNotNull($result);
        $this->assertDatabaseHas('webhook_dead_letters', [
            'provider' => 'mpesa',
            'event_type' => 'stk_callback',
            'error_reason' => 'Invoice not found',
            'error_class' => 'transient',
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_capture_returns_null_when_no_landlord_id(): void
    {
        $result = $this->service->capture(
            WebhookDeadLetter::PROVIDER_MPESA,
            'stk_callback',
            ['amount' => 5000],
            'Some error',
            WebhookDeadLetter::ERROR_TRANSIENT,
            null
        );

        $this->assertNull($result);
        $this->assertDatabaseMissing('webhook_dead_letters', [
            'provider' => 'mpesa',
            'event_type' => 'stk_callback',
        ]);
    }

    public function test_capture_sets_next_retry_at_for_transient_errors(): void
    {
        Mail::fake();

        $result = $this->service->capture(
            WebhookDeadLetter::PROVIDER_PAYSTACK,
            'charge.success',
            ['reference' => 'PAY-123'],
            'Database error',
            WebhookDeadLetter::ERROR_TRANSIENT,
            $this->landlord->id
        );

        $this->assertNotNull($result->next_retry_at);
        $this->assertTrue($result->next_retry_at->isFuture());
        $this->assertEquals(5, $result->max_retries);
    }

    public function test_capture_sets_zero_max_retries_for_permanent_errors(): void
    {
        Mail::fake();

        $result = $this->service->capture(
            WebhookDeadLetter::PROVIDER_PAYSTACK,
            'charge.success',
            ['reference' => 'PAY-123'],
            'Schema violation',
            WebhookDeadLetter::ERROR_PERMANENT,
            $this->landlord->id
        );

        $this->assertNull($result->next_retry_at);
        $this->assertEquals(0, $result->max_retries);
    }

    public function test_capture_sends_email_alert_to_landlord_and_admin(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin@test.com']);

        $this->service->capture(
            WebhookDeadLetter::PROVIDER_MPESA,
            'stk_callback',
            ['amount' => 5000],
            'Processing error',
            WebhookDeadLetter::ERROR_TRANSIENT,
            $this->landlord->id
        );

        Mail::assertQueued(FailedWebhookAlert::class, function (FailedWebhookAlert $mail) use ($admin) {
            return $mail->hasTo($this->landlord->email)
                && $mail->hasTo($admin->email);
        });
    }

    public function test_capture_throttles_alerts_per_provider_per_landlord(): void
    {
        Mail::fake();

        $this->service->capture(
            WebhookDeadLetter::PROVIDER_MPESA,
            'stk_callback',
            ['amount' => 5000],
            'Error 1',
            WebhookDeadLetter::ERROR_TRANSIENT,
            $this->landlord->id
        );

        $this->service->capture(
            WebhookDeadLetter::PROVIDER_MPESA,
            'stk_callback',
            ['amount' => 6000],
            'Error 2',
            WebhookDeadLetter::ERROR_TRANSIENT,
            $this->landlord->id
        );

        Mail::assertQueued(FailedWebhookAlert::class, 1);
        $this->assertDatabaseCount('webhook_dead_letters', 2);
    }

    public function test_capture_sends_only_to_landlord_when_no_super_admin_exists(): void
    {
        Mail::fake();

        $this->service->capture(
            WebhookDeadLetter::PROVIDER_INTASEND,
            'payment.complete',
            ['ref' => 'INT-123'],
            'Timeout',
            WebhookDeadLetter::ERROR_TRANSIENT,
            $this->landlord->id
        );

        Mail::assertQueued(FailedWebhookAlert::class, function (FailedWebhookAlert $mail) {
            return $mail->hasTo($this->landlord->email);
        });
    }

    public function test_capture_sanitizes_phone_numbers_in_payload(): void
    {
        Mail::fake();

        $result = $this->service->capture(
            WebhookDeadLetter::PROVIDER_MPESA,
            'stk_callback',
            ['phone' => '254712345678', 'amount' => 5000],
            'Error',
            WebhookDeadLetter::ERROR_TRANSIENT,
            $this->landlord->id
        );

        $payload = $result->payload;
        $this->assertStringEndsWith('5678', $payload['phone']);
        $this->assertStringStartsWith('*', $payload['phone']);
        $this->assertEquals(5000, $payload['amount']);
    }

    public function test_capture_sanitizes_secret_keys_in_payload(): void
    {
        Mail::fake();

        $result = $this->service->capture(
            WebhookDeadLetter::PROVIDER_PAYSTACK,
            'charge.success',
            [
                'authorization' => 'AUTH_abc123def456',
                'secret' => 'sk_live_supersecretkey',
                'reference' => 'PAY-123',
            ],
            'Error',
            WebhookDeadLetter::ERROR_PERMANENT,
            $this->landlord->id
        );

        $payload = $result->payload;
        $this->assertEquals('***REDACTED***', $payload['authorization']);
        $this->assertEquals('***REDACTED***', $payload['secret']);
        $this->assertEquals('PAY-123', $payload['reference']);
    }

    public function test_resolve_delegates_to_model_mark_resolved(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $deadLetter = WebhookDeadLetter::factory()->forLandlord($this->landlord)->create();

        $this->service->resolve($deadLetter, $admin, 'Manually reconciled');

        $deadLetter->refresh();
        $this->assertNotNull($deadLetter->resolved_at);
        $this->assertEquals($admin->id, $deadLetter->resolved_by);
        $this->assertEquals('Manually reconciled', $deadLetter->resolution_notes);
    }
}
