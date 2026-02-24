<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\NotificationMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationMailContentSafetyTest extends TestCase
{
    private function renderMail(
        string $message = 'Test notification body.',
        ?array $data = null,
        string $recipientName = 'Jane Doe',
        string $subject = 'Test Notification'
    ): string {
        $recipient = User::factory()->make([
            'role' => 'tenant',
            'id' => 1,
            'name' => $recipientName,
        ]);

        $mailable = new NotificationMail(
            notificationSubject: $subject,
            notificationMessage: $message,
            data: $data,
            recipient: $recipient
        );

        return $mailable->render();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function xssPayloadProvider(): array
    {
        return [
            'script tag' => ['<script>alert(\'xss\')</script>', '&lt;script&gt;'],
            'img onerror' => ['<img onerror=alert(1) src=x>', '&lt;img onerror='],
            'svg onload' => ['<svg onload=alert(1)>', '&lt;svg onload='],
            'div event handler' => ['<div onmouseover=alert(1)>', '&lt;div onmouseover='],
            'base64 data uri' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">click</a>', '&lt;a href='],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">', '&lt;meta http-equiv='],
            'iframe srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>">', '&lt;iframe srcdoc='],
            'css expression' => ['<div style="background:expression(alert(1))">', '&lt;div style='],
            'malformed unclosed tag' => ['<img src="x" onerror="alert(1)"', '&lt;img src='],
        ];
    }

    #[DataProvider('xssPayloadProvider')]
    public function test_xss_payload_in_message_body_is_escaped(string $payload, string $escapedFragment): void
    {
        $html = $this->renderMail(message: $payload);

        $this->assertStringNotContainsString($payload, $html, 'Raw XSS payload must not appear in rendered HTML');
        $this->assertStringContainsString($escapedFragment, $html, 'Escaped tag must appear in rendered HTML (proves data flowed through)');
    }

    #[DataProvider('xssPayloadProvider')]
    public function test_xss_payload_in_data_table_values_is_escaped(string $payload, string $escapedFragment): void
    {
        $html = $this->renderMail(data: ['detail' => $payload]);

        $this->assertStringNotContainsString($payload, $html, 'Raw XSS payload must not appear in data table');
        $this->assertStringContainsString($escapedFragment, $html, 'Escaped tag must appear in data table cell');
    }

    public function test_empty_message_renders_without_error(): void
    {
        $html = $this->renderMail(message: '');

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('class="panel"', $html);
        $this->assertStringContainsString('Jane Doe', $html);
    }

    public function test_whitespace_and_newlines_only_message_renders(): void
    {
        $html = $this->renderMail(message: "  \n\n  \n  ");

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('class="panel"', $html);
        $this->assertMatchesRegularExpression('/<br\s*\/?>/', $html);
    }

    public function test_data_with_null_and_empty_values_handles_gracefully(): void
    {
        $html = $this->renderMail(data: [
            'unit' => 'A-101',
            'note' => '',
            'comment' => null,
        ]);

        $this->assertStringContainsString('A-101', $html);
        $this->assertStringContainsString('Note', $html, 'Empty string is scalar — key should render');
        $this->assertStringNotContainsString('Comment', $html, 'null is not scalar — key should be filtered out');
    }

    public function test_data_with_nested_arrays_does_not_render_nested_values(): void
    {
        $html = $this->renderMail(data: [
            'unit' => 'B-202',
            'metadata' => ['floor' => 3, 'wing' => 'East'],
            'tags' => ['urgent', 'maintenance'],
        ]);

        $this->assertStringContainsString('B-202', $html);
        $this->assertStringNotContainsString('Metadata', $html);
        $this->assertStringNotContainsString('floor', $html);
        $this->assertStringNotContainsString('East', $html);
        $this->assertStringNotContainsString('Tags', $html);
        $this->assertStringNotContainsString('urgent', $html);
    }

    public function test_template_rendered_html_placeholders_are_escaped_in_email(): void
    {
        $template = NotificationTemplate::make([
            'name' => 'Test Template',
            'slug' => 'test-safety-template',
            'type' => 'rent_reminder',
            'subject' => 'Reminder for {{tenant_name}}',
            'body' => 'Dear {{tenant_name}}, your rent of {{rent_amount}} is due.',
            'available_placeholders' => ['tenant_name', 'rent_amount'],
            'is_default' => false,
            'is_active' => true,
        ]);

        $rendered = $template->render([
            'tenant_name' => '<script>alert("pwned")</script>',
            'rent_amount' => '25,000 KES',
        ]);

        $this->assertStringContainsString('<script>', $rendered['body'], 'render() must NOT escape — raw HTML expected at this stage');
        $this->assertStringContainsString('<script>', $rendered['subject'], 'render() must NOT escape subject either');

        $html = $this->renderMail(
            message: $rendered['body'],
            subject: $rendered['subject'],
        );

        $this->assertStringNotContainsString('<script>', $html, 'Script tag must be entity-encoded in final email HTML');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('25,000 KES', $html, 'Legitimate content must survive escaping');
    }
}
