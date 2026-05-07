<?php

namespace Tests\Unit\Services;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

class NotificationServiceTest extends TestCase
{
    use CreatesTestData, MocksExternalServices, RefreshDatabase;

    private NotificationService $service;

    private User $landlord;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NotificationService::class);

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first()
        );
    }

    protected function tearDown(): void
    {
        RateLimiter::clear("notifications:{$this->landlord->id}:email:hourly");
        RateLimiter::clear("notifications:{$this->landlord->id}:email:daily");
        RateLimiter::clear("notifications:{$this->landlord->id}:whatsapp:hourly");
        RateLimiter::clear("notifications:{$this->landlord->id}:whatsapp:daily");
        RateLimiter::clear("notifications:{$this->landlord->id}:sms:hourly");
        RateLimiter::clear("notifications:{$this->landlord->id}:sms:daily");
        RateLimiter::clear("notifications:{$this->landlord->id}:push:hourly");
        RateLimiter::clear("notifications:{$this->landlord->id}:push:daily");

        parent::tearDown();
    }

    public function test_get_channels_for_urgency_critical(): void
    {
        $channels = $this->service->getChannelsForUrgency(Notification::URGENCY_CRITICAL);

        $this->assertEquals([
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_SMS,
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_IN_APP,
        ], $channels);
    }

    public function test_get_channels_for_urgency_urgent(): void
    {
        $channels = $this->service->getChannelsForUrgency(Notification::URGENCY_URGENT);

        $this->assertEquals([
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_IN_APP,
        ], $channels);
    }

    public function test_get_channels_for_urgency_important(): void
    {
        $channels = $this->service->getChannelsForUrgency(Notification::URGENCY_IMPORTANT);

        $this->assertEquals([
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_IN_APP,
        ], $channels);
    }

    public function test_get_channels_for_urgency_informational(): void
    {
        $channels = $this->service->getChannelsForUrgency(Notification::URGENCY_INFORMATIONAL);

        $this->assertEquals([
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_IN_APP,
        ], $channels);
    }

    public function test_send_creates_notification_record(): void
    {
        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'in_app_enabled' => true,
        ]);

        $result = $this->service->send(
            $this->tenant->id,
            'general',
            'Test Subject',
            'Test Message',
            null,
            $this->landlord->id
        );

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $this->tenant->id,
            'type' => 'general',
            'subject' => 'Test Subject',
        ]);
    }

    public function test_quiet_hours_returns_true_during_quiet_period(): void
    {
        $prefs = $this->createNotificationPreference($this->tenant, $this->landlord, [
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ]);

        Carbon::setTestNow(Carbon::parse('23:30'));
        $this->assertTrue($prefs->isInQuietHours(now()));

        Carbon::setTestNow(Carbon::parse('03:00'));
        $this->assertTrue($prefs->isInQuietHours(now()));
    }

    public function test_quiet_hours_handles_overnight_period(): void
    {
        $prefs = $this->createNotificationPreference($this->tenant, $this->landlord, [
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2024-01-15 23:00'));
        $this->assertTrue($prefs->isInQuietHours(now()));

        Carbon::setTestNow(Carbon::parse('2024-01-16 01:00'));
        $this->assertTrue($prefs->isInQuietHours(now()));

        Carbon::setTestNow(Carbon::parse('2024-01-16 09:00'));
        $this->assertFalse($prefs->isInQuietHours(now()));
    }

    public function test_quiet_hours_returns_false_when_disabled(): void
    {
        $prefs = $this->createNotificationPreference($this->tenant, $this->landlord, [
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ]);

        Carbon::setTestNow(Carbon::parse('23:30'));
        $this->assertFalse($prefs->isInQuietHours(now()));
    }

    public function test_rate_limit_allows_within_hourly_limit(): void
    {
        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'in_app_enabled' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $result = $this->service->send(
                $this->tenant->id,
                'general',
                'Test Subject',
                'Test Message',
                null,
                $this->landlord->id
            );

            $this->assertArrayNotHasKey('error', $result);
        }
    }

    public function test_in_app_channel_bypasses_rate_limits(): void
    {
        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => false,
            'whatsapp_enabled' => false,
            'sms_enabled' => false,
            'push_enabled' => false,
            'in_app_enabled' => true,
        ]);

        for ($i = 0; $i < 150; $i++) {
            $result = $this->service->send(
                $this->tenant->id,
                'general',
                "Test Subject $i",
                'Test Message',
                null,
                $this->landlord->id
            );
        }

        $lastResult = $this->service->send(
            $this->tenant->id,
            'general',
            'Final Test',
            'Test Message',
            null,
            $this->landlord->id
        );

        $this->assertArrayHasKey('in_app', $lastResult);
        $this->assertEquals('sent', $lastResult['in_app']);
    }

    public function test_deferred_notification_creates_pending_record(): void
    {
        Queue::fake();

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2024-01-15 23:00'));

        $this->service->send(
            $this->tenant->id,
            'general',
            'Test Subject',
            'Test Message',
            null,
            $this->landlord->id
        );

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $this->tenant->id,
            'status' => 'pending',
            'quiet_hours_suppressed' => true,
        ]);
    }

    public function test_critical_notifications_bypass_quiet_hours(): void
    {
        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'eviction_notice_enabled' => true,
            'in_app_enabled' => true,
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2024-01-15 23:00'));

        $result = $this->service->send(
            $this->tenant->id,
            'eviction_notice',
            'Urgent Notice',
            'Critical message',
            null,
            $this->landlord->id
        );

        $this->assertArrayNotHasKey('quiet_hours_suppressed', $result);
        $this->assertArrayNotHasKey('scheduled_for', $result);
    }

    public function test_send_in_app_only_creates_in_app_notification(): void
    {
        $result = $this->service->sendInAppOnly(
            $this->tenant->id,
            'general',
            'Test Subject',
            'Test Message',
            null,
            $this->landlord->id
        );

        $this->assertArrayHasKey('in_app', $result);
        $this->assertEquals('sent', $result['in_app']);

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $this->tenant->id,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);
    }

    public function test_notify_landlord_unreachable_creates_in_app_notification(): void
    {
        $failedNotification = Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'whatsapp',
            'subject' => 'Rent Reminder',
            'message' => 'Your rent is due',
            'status' => 'failed',
            'error_message' => 'Invalid phone number',
        ]);

        $this->service->notifyLandlordUnreachable($failedNotification);

        $this->assertDatabaseHas('notifications', [
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->landlord->id,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);

        $landlordNotification = Notification::where('recipient_id', $this->landlord->id)
            ->where('channel', 'in_app')
            ->latest()
            ->first();

        $this->assertStringContainsString('Tenant Unreachable', $landlordNotification->subject);
        $this->assertStringContainsString($this->tenant->name, $landlordNotification->message);
    }

    public function test_send_bulk_sends_to_multiple_recipients(): void
    {
        $tenant2 = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'in_app_enabled' => true,
        ]);
        $this->createNotificationPreference($tenant2, $this->landlord, [
            'general_enabled' => true,
            'in_app_enabled' => true,
        ]);

        $result = $this->service->sendBulk(
            [$this->tenant->id, $tenant2->id],
            'general',
            'Bulk Subject',
            'Bulk Message',
            null,
            $this->landlord->id
        );

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['sent']);
    }

    public function test_send_email_dispatches_notification_mail_mailable(): void
    {
        Mail::fake();

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
        ]);

        $this->service->send(
            $this->tenant->id,
            'general',
            'Test Subject',
            'Test Message',
            ['key' => 'value'],
            $this->landlord->id
        );

        Mail::assertSent(NotificationMail::class);
    }

    public function test_send_email_passes_correct_data_to_notification_mail(): void
    {
        Mail::fake();

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
        ]);

        $data = ['amount' => '5000', 'unit' => 'A1'];

        $this->service->send(
            $this->tenant->id,
            'general',
            'Rent Reminder',
            'Your rent is due',
            $data,
            $this->landlord->id
        );

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($data) {
            return $mail->notificationSubject === 'Rent Reminder'
                && $mail->notificationMessage === 'Your rent is due'
                && $mail->data === $data
                && $mail->recipient->id === $this->tenant->id
                && $mail->hasTo($this->tenant->email)
                && $mail->hasSubject('Rent Reminder');
        });
    }

    public function test_send_email_marks_notification_as_sent_on_success(): void
    {
        Mail::fake();

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
        ]);

        $this->service->send(
            $this->tenant->id,
            'general',
            'Test Subject',
            'Test Message',
            null,
            $this->landlord->id
        );

        $notification = Notification::where('recipient_id', $this->tenant->id)
            ->where('channel', 'email')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals(\App\Enums\NotificationStatus::Sent, $notification->status);
        $this->assertNotNull($notification->sent_at);
    }

    public function test_send_email_marks_notification_as_failed_on_exception(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
        ]);

        $this->service->send(
            $this->tenant->id,
            'general',
            'Test Subject',
            'Test Message',
            null,
            $this->landlord->id
        );

        $notification = Notification::where('recipient_id', $this->tenant->id)
            ->where('channel', 'email')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals(\App\Enums\NotificationStatus::Failed, $notification->status);
        $this->assertStringContainsString('Connection refused', $notification->error_message);
    }
}
