<?php

namespace Tests\Unit\Mail;

use App\Mail\FailedWebhookAlert;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailedWebhookAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailable_has_correct_subject_per_provider(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $mpesaDl = WebhookDeadLetter::factory()->mpesa()->forLandlord($landlord)->create();
        $paystackDl = WebhookDeadLetter::factory()->paystack()->forLandlord($landlord)->create();

        $mpesaMail = new FailedWebhookAlert($mpesaDl);
        $paystackMail = new FailedWebhookAlert($paystackDl);

        $this->assertEquals('Webhook Failure Alert - Mpesa', $mpesaMail->envelope()->subject);
        $this->assertEquals('Webhook Failure Alert - Paystack', $paystackMail->envelope()->subject);
    }

    public function test_mailable_renders_error_details(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $deadLetter = WebhookDeadLetter::factory()->mpesa()->forLandlord($landlord)->create([
            'error_reason' => 'Invoice not found',
            'error_class' => WebhookDeadLetter::ERROR_PERMANENT,
        ]);

        $mailable = new FailedWebhookAlert($deadLetter);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Invoice not found', $rendered);
        $this->assertStringContainsString('Mpesa', $rendered);
        $this->assertStringContainsString('requires manual review', $rendered);
    }

    public function test_mailable_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(FailedWebhookAlert::class))
        );
    }

    public function test_mailable_has_after_commit_true(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $deadLetter = WebhookDeadLetter::factory()->forLandlord($landlord)->create();

        $mailable = new FailedWebhookAlert($deadLetter);

        $this->assertTrue($mailable->afterCommit);
    }
}
