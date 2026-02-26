<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

class NotificationEmailStandardizationTest extends TestCase
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
        ['tenant' => $this->tenant] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first()
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->landlord)) {
            RateLimiter::clear("notifications:{$this->landlord->id}:email:hourly");
            RateLimiter::clear("notifications:{$this->landlord->id}:email:daily");
        }

        parent::tearDown();
    }

    public function test_rent_reminder_to_tenant_uses_standardized_layout(): void
    {
        Mail::fake();

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'rent_reminder_enabled' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
        ]);

        $this->service->sendRentReminder(
            $this->tenant->id,
            ['amount' => 15000, 'due_date' => '2026-03-01'],
            $this->landlord->id
        );

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) {
            return $mail->hasTo($this->tenant->email)
                && $mail->hasSubject('Rent Reminder - Due 2026-03-01');
        });

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $this->tenant->id,
            'channel' => 'email',
            'status' => 'sent',
            'type' => 'rent_reminder',
        ]);

        $mailable = new NotificationMail(
            'Rent Reminder - Due 2026-03-01',
            "Hello {$this->tenant->name},\n\nYour rent is due on 2026-03-01.",
            ['amount' => '15,000', 'due_date' => '2026-03-01'],
            $this->tenant
        );
        $html = $mailable->render();

        $this->assertStringContainsString('content-cell', $html);
        $this->assertStringContainsString('Hello '.$this->tenant->name, $html);
        $this->assertStringContainsString('panel', $html);
        $this->assertStringContainsString('email/preferences', $html);
        $this->assertStringContainsString('signature=', $html);
    }

    public function test_arrears_data_table_renders_in_email(): void
    {
        $mailable = new NotificationMail(
            'Payment Overdue',
            'You have outstanding arrears. Please clear your balance.',
            [
                'arrears_amount' => '15,000',
                'days_overdue' => '30',
                'action_url' => 'https://app.test/pay',
                'action_text' => 'Pay Now',
            ],
            $this->tenant
        );
        $html = $mailable->render();

        $this->assertStringContainsString('Arrears Amount', $html);
        $this->assertStringContainsString('15,000', $html);
        $this->assertStringContainsString('Days Overdue', $html);
        $this->assertStringContainsString('30', $html);

        $this->assertStringNotContainsString('Action Url', $html);
        $this->assertStringNotContainsString('Action Text', $html);

        $this->assertStringContainsString('https://app.test/pay', $html);
        $this->assertStringContainsString('Pay Now', $html);
        $this->assertStringContainsString('button', $html);
    }

    public function test_notification_to_landlord_has_settings_unsubscribe_url(): void
    {
        Mail::fake();

        $this->createNotificationPreference($this->landlord, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
        ]);

        $this->service->send(
            $this->landlord->id,
            'general',
            'Test Subject',
            'Test message for landlord.',
            null,
            $this->landlord->id
        );

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) {
            return $mail->hasTo($this->landlord->email);
        });

        $mailable = new NotificationMail(
            'Test Subject',
            'Test message for landlord.',
            null,
            $this->landlord
        );
        $html = $mailable->render();

        $this->assertStringContainsString('notifications/settings', $html);
        $this->assertStringNotContainsString('email/preferences', $html);
        $this->assertStringNotContainsString('signature=', $html);
    }

    public function test_bulk_send_creates_unique_signed_urls_per_recipient(): void
    {
        Mail::fake();

        $tenant2 = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
        ]);
        $this->createNotificationPreference($tenant2, $this->landlord, [
            'general_enabled' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
        ]);

        $this->service->sendBulk(
            [$this->tenant->id, $tenant2->id],
            'general',
            'Bulk Notice',
            'A message for all tenants.',
            null,
            $this->landlord->id
        );

        $sentMails = [];
        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use (&$sentMails) {
            $sentMails[] = $mail;

            return true;
        });

        $this->assertCount(2, $sentMails);

        $html1 = $sentMails[0]->render();
        $html2 = $sentMails[1]->render();

        $this->assertStringContainsString('email/preferences', $html1);
        $this->assertStringContainsString('email/preferences', $html2);

        $this->assertStringContainsString('user='.$this->tenant->id, $html1);
        $this->assertStringContainsString('user='.$tenant2->id, $html2);
    }

    public function test_notification_with_action_url_renders_button(): void
    {
        $mailable = new NotificationMail(
            'Action Required',
            'Please click the button below.',
            [
                'action_url' => 'https://example.com/pay',
                'action_text' => 'Pay Now',
                'invoice_number' => 'INV-001',
            ],
            $this->tenant
        );
        $html = $mailable->render();

        $this->assertStringContainsString('https://example.com/pay', $html);
        $this->assertStringContainsString('Pay Now', $html);
        $this->assertStringContainsString('button', $html);

        $this->assertStringNotContainsString('Action Url', $html);
        $this->assertStringNotContainsString('Action Text', $html);
        $this->assertStringContainsString('Invoice Number', $html);
        $this->assertStringContainsString('INV-001', $html);
    }

    public function test_notification_without_data_renders_without_table(): void
    {
        $mailable = new NotificationMail(
            'Simple Notification',
            'This is a plain message with no extra data.',
            null,
            $this->tenant
        );
        $html = $mailable->render();

        $this->assertStringNotContainsString('<th', $html);

        $this->assertStringContainsString('Simple Notification', $html);
        $this->assertStringContainsString('This is a plain message with no extra data.', $html);
        $this->assertStringContainsString('Hello '.$this->tenant->name, $html);
        $this->assertStringContainsString(config('app.name'), $html);
    }

    public function test_email_footer_uses_config_app_name(): void
    {
        config(['app.name' => 'TestPropApp']);

        $mailable = new NotificationMail(
            'Footer Test',
            'Checking footer branding.',
            null,
            $this->tenant
        );
        $html = $mailable->render();

        $this->assertStringContainsString('TestPropApp Team', $html);
        $this->assertStringContainsString('TestPropApp', $html);
        $this->assertStringNotContainsString('PropManager Team', $html);
    }

    public function test_notification_template_render_output_passed_to_email(): void
    {
        $template = NotificationTemplate::factory()
            ->rentReminder()
            ->forLandlord($this->landlord)
            ->create();

        $rendered = $template->render([
            'tenant_name' => 'Alice',
            'rent_amount' => '15,000',
            'due_date' => '2026-03-01',
        ]);

        $mailable = new NotificationMail(
            $rendered['subject'],
            $rendered['body'],
            null,
            $this->tenant
        );
        $html = $mailable->render();

        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('15,000', $html);
        $this->assertStringNotContainsString('{{tenant_name}}', $html);
        $this->assertStringNotContainsString('{{rent_amount}}', $html);

        $xssTemplate = NotificationTemplate::factory()
            ->forLandlord($this->landlord)
            ->create([
                'name' => 'XSS Test Template',
                'slug' => 'xss-test-template',
                'type' => 'general',
                'subject' => 'Test XSS',
                'body' => 'Hello {{tenant_name}}, <script>alert("xss")</script>',
            ]);

        $xssRendered = $xssTemplate->render(['tenant_name' => 'Bob']);
        $xssMailable = new NotificationMail(
            $xssRendered['subject'],
            $xssRendered['body'],
            null,
            $this->tenant
        );
        $xssHtml = $xssMailable->render();

        $this->assertStringContainsString('&lt;script&gt;', $xssHtml);
        $this->assertStringNotContainsString('<script>alert', $xssHtml);
    }

    public function test_one_click_unsubscribe_disables_email_for_tenant(): void
    {
        $this->createNotificationPreference($this->tenant, $this->landlord, [
            'email_enabled' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'email.unsubscribe',
            now()->addDays(30),
            ['user' => $this->tenant->id]
        );

        $response = $this->post($url);

        $response->assertOk();
        $response->assertJson(['status' => 'unsubscribed']);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'email_enabled' => false,
        ]);
    }

    public function test_one_click_unsubscribe_rejects_unsigned_request(): void
    {
        $response = $this->post(route('email.unsubscribe', ['user' => $this->tenant->id]));

        $response->assertStatus(403);
    }

    public function test_one_click_unsubscribe_rejects_non_tenant(): void
    {
        $url = URL::temporarySignedRoute(
            'email.unsubscribe',
            now()->addDays(30),
            ['user' => $this->landlord->id]
        );

        $response = $this->post($url);

        $response->assertStatus(403);
    }
}
