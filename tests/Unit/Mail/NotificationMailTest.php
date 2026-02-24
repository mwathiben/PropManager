<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\NotificationMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

class NotificationMailTest extends TestCase
{
    public function test_envelope_has_correct_subject(): void
    {
        $recipient = User::factory()->make(['role' => 'tenant', 'id' => 1]);

        $mailable = new NotificationMail(
            notificationSubject: 'Rent Due Reminder',
            notificationMessage: 'Your rent is due.',
            data: null,
            recipient: $recipient
        );

        $mailable->assertHasSubject('Rent Due Reminder');
    }

    public function test_passes_message_data_and_recipient_to_template(): void
    {
        $recipient = User::factory()->make([
            'role' => 'tenant',
            'id' => 1,
            'name' => 'Jane Doe',
        ]);

        $mailable = new NotificationMail(
            notificationSubject: 'Payment Reminder',
            notificationMessage: 'Please pay your rent.',
            data: ['unit' => 'A-101', 'amount' => '25,000'],
            recipient: $recipient
        );

        $mailable->assertSeeInHtml('Jane Doe');
        $mailable->assertSeeInHtml('Please pay your rent.');
        $mailable->assertSeeInHtml('A-101');
        $mailable->assertSeeInHtml('25,000');
    }

    public function test_tenant_recipient_gets_signed_unsubscribe_url(): void
    {
        $recipient = User::factory()->make(['role' => 'tenant', 'id' => 99]);

        $mailable = new NotificationMail(
            notificationSubject: 'Notice',
            notificationMessage: 'Test message.',
            data: null,
            recipient: $recipient
        );

        $rendered = $mailable->render();

        $this->assertStringContainsString('email/preferences', $rendered);
        $this->assertStringContainsString('signature=', $rendered);
    }

    public function test_landlord_recipient_gets_notifications_settings_url(): void
    {
        $recipient = User::factory()->make(['role' => 'landlord', 'id' => 50]);

        $mailable = new NotificationMail(
            notificationSubject: 'Notice',
            notificationMessage: 'Test message.',
            data: null,
            recipient: $recipient
        );

        $rendered = $mailable->render();

        $this->assertStringContainsString(route('notifications.settings'), $rendered);
    }

    public function test_does_not_implement_should_queue(): void
    {
        $this->assertFalse(
            in_array(ShouldQueue::class, class_implements(NotificationMail::class)),
            'NotificationMail must NOT implement ShouldQueue'
        );
    }

    public function test_renders_without_errors(): void
    {
        $recipient = User::factory()->make(['role' => 'tenant', 'id' => 1]);

        $mailable = new NotificationMail(
            notificationSubject: 'Test Subject',
            notificationMessage: 'Test body content.',
            data: ['key' => 'value'],
            recipient: $recipient
        );

        $rendered = $mailable->render();

        $this->assertNotEmpty($rendered);
        $this->assertStringContainsString('Test Subject', $rendered);
    }
}
