<?php

namespace Tests\Feature;

use App\Jobs\SendBulkNotificationsJob;
use App\Jobs\SendNotificationJob;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected Property $property;

    protected Building $building;

    protected Unit $unit;

    protected User $tenant;

    protected Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        // Create landlord user
        $this->landlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        // Authenticate as landlord for setup
        $this->actingAs($this->landlord);

        // Create property
        $this->property = Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Property',
            'type' => 'residential',
            'address' => '123 Test Street',
        ]);

        // Create building
        $this->building = Building::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Building A',
            'total_floors' => 2,
            'units_per_floor' => 2,
        ]);

        // Create unit
        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'landlord_id' => $this->landlord->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 15000,
        ]);

        // Create tenant
        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
            'email' => 'tenant@example.com',
            'mobile_number' => '+254712345678',
        ]);

        // Create lease
        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(9),
            'rent_amount' => 15000,
            'deposit_amount' => 15000,
            'is_active' => true,
        ]);
    }

    public function test_notifications_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/notifications');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Notifications/Index'));
    }

    public function test_notifications_page_shows_notification_history(): void
    {
        // Create some notifications
        Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'subject' => 'Rent Reminder',
            'message' => 'Your rent is due soon.',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/notifications');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
        );
    }

    public function test_notifications_can_be_filtered_by_type(): void
    {
        // Create notifications of different types
        Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'message' => 'Rent reminder message',
            'status' => 'sent',
        ]);

        Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'arrears_notice',
            'channel' => 'email',
            'message' => 'Arrears notice message',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/notifications?type=rent_reminder');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('filters.type', 'rent_reminder')
        );
    }

    public function test_notifications_can_be_filtered_by_status(): void
    {
        Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'message' => 'Sent message',
            'status' => 'sent',
        ]);

        Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'message' => 'Failed message',
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/notifications?status=failed');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('filters.status', 'failed')
        );
    }

    public function test_can_send_single_notification(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->landlord)
            ->post('/notifications/send', [
                'recipient_id' => $this->tenant->id,
                'type' => 'general',
                'subject' => 'Test Subject',
                'message' => 'Test notification message',
                'send_immediately' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->recipientId === $this->tenant->id
                && $job->type === 'general'
                && $job->subject === 'Test Subject';
        });
    }

    public function test_can_send_immediate_notification(): void
    {
        // Set up notification preferences for the tenant to enable email
        NotificationPreference::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'general_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
        ]);

        $response = $this->actingAs($this->landlord)
            ->post('/notifications/send', [
                'recipient_id' => $this->tenant->id,
                'type' => 'general',
                'subject' => 'Test Subject',
                'message' => 'Test notification message',
                'send_immediately' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check notification was created (status may be 'sent' or 'failed' depending on mail config)
        $notification = Notification::where('recipient_id', $this->tenant->id)
            ->where('type', 'general')
            ->where('channel', 'email')
            ->first();

        $this->assertNotNull($notification, 'Notification should be created in database');
        $this->assertEquals('Test Subject', $notification->subject);
    }

    public function test_can_send_bulk_notifications(): void
    {
        Queue::fake();

        // Create another tenant
        $tenant2 = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $response = $this->actingAs($this->landlord)
            ->post('/notifications/send-bulk', [
                'recipient_ids' => [$this->tenant->id, $tenant2->id],
                'type' => 'rent_reminder',
                'subject' => 'Bulk Rent Reminder',
                'message' => 'Your rent is due soon.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(SendBulkNotificationsJob::class, function ($job) use ($tenant2) {
            return count($job->recipientIds) === 2
                && in_array($this->tenant->id, $job->recipientIds)
                && in_array($tenant2->id, $job->recipientIds);
        });
    }

    public function test_can_send_rent_reminders_to_all_tenants(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->landlord)
            ->post('/notifications/rent-reminders');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // One notification should be queued for the active lease
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_can_send_arrears_notices(): void
    {
        Queue::fake();

        // Update lease to have arrears
        $this->lease->update(['arrears' => 5000]);

        $response = $this->actingAs($this->landlord)
            ->post('/notifications/arrears-notices');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_notification_preferences_can_be_retrieved(): void
    {
        $response = $this->actingAs($this->landlord)
            ->getJson('/notifications/preferences');

        $response->assertStatus(200);
        // Response should contain the preference model attributes
        $response->assertJson([
            'user_id' => $this->landlord->id,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_notification_preferences_can_be_updated(): void
    {
        // First get/create preferences
        NotificationPreference::getOrCreate($this->landlord->id, $this->landlord->id);

        // Route uses POST, not PUT
        $response = $this->actingAs($this->landlord)
            ->post('/notifications/preferences', [
                'email_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false,
                'rent_reminder_days_before' => 5,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->landlord->id,
            'email_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'rent_reminder_days_before' => 5,
        ]);
    }

    public function test_notification_can_be_marked_as_read(): void
    {
        $notification = Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'message' => 'Test message',
            'status' => 'delivered',
        ]);

        $response = $this->actingAs($this->landlord)
            ->post("/notifications/{$notification->id}/mark-read");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
        $this->assertEquals('read', $notification->status);
    }

    public function test_failed_notification_can_be_retried(): void
    {
        Queue::fake();

        $notification = Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'subject' => 'Failed Notification',
            'message' => 'Test message',
            'status' => 'failed',
            'error_message' => 'SMTP connection failed',
        ]);

        $response = $this->actingAs($this->landlord)
            ->post("/notifications/{$notification->id}/retry");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_cannot_retry_non_failed_notification(): void
    {
        $notification = Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'message' => 'Test message',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->landlord)
            ->post("/notifications/{$notification->id}/retry");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_notification_can_be_deleted(): void
    {
        $notification = Notification::create([
            'landlord_id' => $this->landlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'message' => 'Test message',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->landlord)
            ->delete("/notifications/{$notification->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_landlord_cannot_access_other_landlord_notifications(): void
    {
        // Create another landlord
        $otherLandlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        // Create notification as the other landlord
        $this->actingAs($otherLandlord);

        $notification = Notification::create([
            'landlord_id' => $otherLandlord->id,
            'recipient_id' => $this->tenant->id,
            'type' => 'rent_reminder',
            'channel' => 'email',
            'message' => 'Test message',
            'status' => 'sent',
        ]);

        // Try to mark as read as the first landlord
        $response = $this->actingAs($this->landlord)
            ->post("/notifications/{$notification->id}/mark-read");

        // TenantScope filters out other landlord's data, resulting in 404 (not found)
        // This is correct security behavior - don't reveal that the notification exists
        $response->assertStatus(404);
    }

    public function test_send_notification_validates_required_fields(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post('/notifications/send', [
                // Missing required fields
            ]);

        $response->assertSessionHasErrors(['recipient_id', 'type', 'subject', 'message']);
    }

    public function test_send_notification_validates_type(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post('/notifications/send', [
                'recipient_id' => $this->tenant->id,
                'type' => 'invalid_type',
                'subject' => 'Test',
                'message' => 'Test message',
            ]);

        $response->assertSessionHasErrors(['type']);
    }

    public function test_caretaker_can_send_notifications_for_their_landlord(): void
    {
        Queue::fake();

        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        $response = $this->actingAs($caretaker)
            ->post('/notifications/send', [
                'recipient_id' => $this->tenant->id,
                'type' => 'general',
                'subject' => 'Caretaker Message',
                'message' => 'Message from caretaker',
                'send_immediately' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        auth()->logout();

        $response = $this->get('/notifications');

        $response->assertRedirect('/login');
    }
}
