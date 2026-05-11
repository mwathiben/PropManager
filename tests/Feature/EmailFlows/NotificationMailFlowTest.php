<?php

declare(strict_types=1);

namespace Tests\Feature\EmailFlows;

use App\Mail\NotificationMail;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\InteractsWithMailpit;

class NotificationMailFlowTest extends TestCase
{
    use InteractsWithMailpit, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
        config(['app.name' => 'PropManager']);
    }

    public function test_tenant_notification_captured_with_rfc8058_headers(): void
    {
        $scenario = $this->createTenantWithLandlord();
        $tenant = $scenario['tenant'];

        $mailable = new NotificationMail(
            notificationSubject: 'Rent Reminder',
            notificationMessage: 'Your rent of KSh 25,000 is due on March 1st.',
            data: [
                'unit' => 'A-101',
                'amount' => 'KSh 25,000',
                'due_date' => 'March 1, 2026',
            ],
            recipient: $tenant,
        );

        Mail::to($tenant->email)->send($mailable);

        $this->assertEmailSentTo($tenant->email, 'Rent Reminder');
        $this->assertEmailCountFor($tenant->email, 1);

        $messages = $this->mailpit->searchByRecipient($tenant->email);
        $headers = $this->mailpit->getMessageHeaders($messages[0]['ID']);

        $this->assertArrayHasKey('List-Unsubscribe', $headers);
        $this->assertArrayHasKey('List-Unsubscribe-Post', $headers);

        $listUnsubscribe = $headers['List-Unsubscribe'][0];
        $this->assertStringStartsWith('<', $listUnsubscribe);
        $this->assertStringEndsWith('>', $listUnsubscribe);
        $this->assertStringContainsString('email/unsubscribe', $listUnsubscribe);
        $this->assertStringContainsString('signature=', $listUnsubscribe);

        $listUnsubscribePost = $headers['List-Unsubscribe-Post'][0];
        $this->assertEquals('List-Unsubscribe=One-Click', $listUnsubscribePost);

        $html = $this->getLatestEmailHtmlFor($tenant->email);
        $decoded = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString($tenant->name, $decoded);
        $this->assertStringContainsString('A-101', $decoded);
        $this->assertStringContainsString('KSh 25,000', $decoded);
        $this->assertStringContainsString('March 1, 2026', $decoded);
        $this->assertStringContainsString('PropManager', $decoded);

        $links = $this->getLatestEmailLinksFor($tenant->email);
        $this->assertSignedUnsubscribeLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($decoded));
        $this->assertStringNotContainsString('APP_KEY', $decoded);

        $appKey = config('app.key');
        $this->assertNotEmpty($appKey, 'app.key must be set for this assertion to be meaningful');
        $this->assertStringNotContainsString($appKey, $decoded);
    }

    public function test_data_table_filters_excluded_keys_and_non_scalar(): void
    {
        $scenario = $this->createTenantWithLandlord();
        $tenant = $scenario['tenant'];

        $mailable = new NotificationMail(
            notificationSubject: 'Payment Update',
            notificationMessage: 'Your payment has been processed.',
            data: [
                'unit' => 'B-202',
                'amount' => '15,000 KES',
                'action_url' => 'https://example.com/pay',
                'action_text' => 'Pay Now',
                'nested' => ['should', 'be', 'filtered'],
            ],
            recipient: $tenant,
        );

        Mail::to($tenant->email)->send($mailable);

        $this->assertEmailSentTo($tenant->email, 'Payment Update');

        $html = $this->getLatestEmailHtmlFor($tenant->email);
        $decoded = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('B-202', $decoded);
        $this->assertStringContainsString('15,000 KES', $decoded);

        $this->assertStringNotContainsString('Action Url', $decoded);
        $this->assertStringNotContainsString('Action Text', $decoded);
        $this->assertStringNotContainsString('Nested', $decoded);

        $this->assertStringContainsString('Pay Now', $decoded);
        $this->assertStringContainsString('https://example.com/pay', $decoded);
    }

    public function test_action_button_rejects_javascript_url(): void
    {
        // Two distinct tenants so each mailable can be retrieved by
        // recipient without a Mailpit deleteAll in between — that wipe
        // is unsafe under --parallel (other workers see it too).
        $maliciousTenant = $this->createTenantWithLandlord()['tenant'];
        $safeTenant = $this->createTenantWithLandlord()['tenant'];

        Mail::to($maliciousTenant->email)->send(new NotificationMail(
            notificationSubject: 'XSS Action Test',
            notificationMessage: 'Testing action button security.',
            data: [
                'action_url' => 'javascript:alert(1)',
                'action_text' => 'Click Me',
            ],
            recipient: $maliciousTenant,
        ));

        Mail::to($safeTenant->email)->send(new NotificationMail(
            notificationSubject: 'Safe Action Test',
            notificationMessage: 'Testing safe action button.',
            data: [
                'action_url' => 'https://propmanager.test/dashboard',
                'action_text' => 'Go to Dashboard',
            ],
            recipient: $safeTenant,
        ));

        $this->assertEmailSentTo($maliciousTenant->email, 'XSS Action Test');
        $maliciousDecoded = html_entity_decode(
            $this->getLatestEmailHtmlFor($maliciousTenant->email),
            ENT_QUOTES,
            'UTF-8',
        );
        $this->assertStringNotContainsString('javascript:', $maliciousDecoded);

        $this->assertEmailSentTo($safeTenant->email, 'Safe Action Test');
        $safeDecoded = html_entity_decode(
            $this->getLatestEmailHtmlFor($safeTenant->email),
            ENT_QUOTES,
            'UTF-8',
        );
        $this->assertStringContainsString('Go to Dashboard', $safeDecoded);
        $this->assertStringContainsString('https://propmanager.test/dashboard', $safeDecoded);
    }

    public function test_xss_in_message_body_escaped(): void
    {
        $scenario = $this->createTenantWithLandlord();
        $tenant = $scenario['tenant'];

        $mailable = new NotificationMail(
            notificationSubject: 'Body XSS Test',
            notificationMessage: '<script>alert("xss")</script> Your rent is due.',
            data: null,
            recipient: $tenant,
        );

        Mail::to($tenant->email)->send($mailable);

        $this->assertEmailSentTo($tenant->email, 'Body XSS Test');

        $html = $this->getLatestEmailHtmlFor($tenant->email);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Your rent is due.', $html);
    }

    public function test_landlord_gets_settings_route_not_signed_url(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $mailable = new NotificationMail(
            notificationSubject: 'Landlord Notification',
            notificationMessage: 'Your property has a new tenant application.',
            data: [
                'property' => 'Sunset Apartments',
                'applicant' => 'Jane Doe',
            ],
            recipient: $landlord,
        );

        Mail::to($landlord->email)->send($mailable);

        $this->assertEmailSentTo($landlord->email, 'Landlord Notification');
        $this->assertEmailCountFor($landlord->email, 1);

        $messages = $this->mailpit->searchByRecipient($landlord->email);
        $headers = $this->mailpit->getMessageHeaders($messages[0]['ID']);

        $this->assertArrayHasKey('List-Unsubscribe', $headers);
        $listUnsubscribe = $headers['List-Unsubscribe'][0];
        $this->assertStringContainsString('notifications/settings', $listUnsubscribe);
        $this->assertStringNotContainsString('signature=', $listUnsubscribe);

        $this->assertArrayNotHasKey(
            'List-Unsubscribe-Post',
            $headers,
            'Landlord emails must not have List-Unsubscribe-Post (GET route is not RFC 8058 compliant)',
        );

        $html = $this->getLatestEmailHtmlFor($landlord->email);
        $decoded = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString($landlord->name, $decoded);
        $this->assertStringContainsString('Sunset Apartments', $decoded);
        $this->assertStringContainsString('Jane Doe', $decoded);
        $this->assertStringContainsString('PropManager', $decoded);

        $links = $this->getLatestEmailLinksFor($landlord->email);
        $this->assertSettingsLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($decoded));
        $this->assertStringNotContainsString('APP_KEY', $decoded);

        $appKey = config('app.key');
        $this->assertNotEmpty($appKey, 'app.key must be set for this assertion to be meaningful');
        $this->assertStringNotContainsString($appKey, $decoded);
    }

    private function createTenantWithLandlord(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()->forProperty($property)->create();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);

        return compact('landlord', 'tenant', 'building', 'unit', 'lease');
    }

    private function assertSignedUnsubscribeLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, 'email/preferences') && str_contains($link, 'signature=')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Signed unsubscribe URL not found in email body links');
    }

    private function assertSettingsLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, 'notifications/settings')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Notifications settings link not found in email body links');
    }
}
