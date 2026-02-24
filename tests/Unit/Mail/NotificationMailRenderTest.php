<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\NotificationMail;
use App\Models\User;
use Tests\TestCase;

class NotificationMailRenderTest extends TestCase
{
    private function renderMail(
        string $message = 'Test notification body.',
        ?array $data = null,
        string $recipientRole = 'tenant',
        string $recipientName = 'Jane Doe',
        string $subject = 'Test Notification'
    ): string {
        $recipient = User::factory()->make([
            'role' => $recipientRole,
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

    public function test_uses_vendor_mail_layout_structure(): void
    {
        $html = $this->renderMail();

        $this->assertStringContainsString('class="wrapper"', $html);
        $this->assertStringContainsString('class="content-cell"', $html);
        $this->assertStringNotContainsString('class="message-box"', $html, 'Old standalone .message-box class must not remain');
        $this->assertStringNotContainsString('linear-gradient', $html, 'Old standalone gradient header must not remain');
    }

    public function test_greeting_shows_recipient_name(): void
    {
        $html = $this->renderMail(recipientName: 'Alice Wanjiku');

        $this->assertStringContainsString('Alice Wanjiku', $html);
    }

    public function test_message_body_in_panel_with_escaped_html_and_line_breaks(): void
    {
        $html = $this->renderMail(message: "Line one.\nLine two.");

        $this->assertStringContainsString('class="panel"', $html);
        $this->assertMatchesRegularExpression('/Line one\.<br\s*\/?>/', $html);
    }

    public function test_data_table_renders_scalar_key_value_pairs(): void
    {
        $html = $this->renderMail(data: [
            'unit_number' => 'A-101',
            'rent_amount' => '25,000 KES',
        ]);

        $this->assertStringContainsString('Unit Number', $html);
        $this->assertStringContainsString('A-101', $html);
        $this->assertStringContainsString('Rent Amount', $html);
        $this->assertStringContainsString('25,000 KES', $html);
    }

    public function test_data_table_excludes_non_scalar_values(): void
    {
        $html = $this->renderMail(data: [
            'visible_key' => 'visible_value',
            'nested_data' => ['should', 'not', 'render'],
        ]);

        $this->assertStringContainsString('visible_value', $html);
        $this->assertStringNotContainsString('Nested Data', $html);
    }

    public function test_data_table_excludes_internal_action_keys(): void
    {
        $html = $this->renderMail(data: [
            'unit' => 'B-202',
            'action_url' => 'https://example.com/pay',
            'action_text' => 'Pay Now',
        ]);

        $this->assertStringContainsString('B-202', $html);
        $this->assertStringNotContainsString('Action Url', $html);
        $this->assertStringNotContainsString('Action Text', $html);
    }

    public function test_action_button_renders_when_action_url_present(): void
    {
        $html = $this->renderMail(data: [
            'action_url' => 'https://example.com/pay',
            'action_text' => 'Pay Now',
        ]);

        $this->assertStringContainsString('class="button', $html);
        $this->assertStringContainsString('https://example.com/pay', $html);
        $this->assertStringContainsString('Pay Now', $html);
    }

    public function test_action_button_omitted_when_no_action_url(): void
    {
        $html = $this->renderMail(data: ['unit' => 'C-303']);

        $this->assertStringNotContainsString('class="button', $html);
    }

    public function test_footer_uses_config_app_name_not_hardcoded(): void
    {
        config(['app.name' => 'TestAppName']);

        $html = $this->renderMail();

        $this->assertStringContainsString('TestAppName Team', $html);
    }

    public function test_xss_in_message_body_is_neutralized(): void
    {
        $html = $this->renderMail(message: '<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_empty_data_array_renders_no_table(): void
    {
        $html = $this->renderMail(data: []);

        $this->assertStringNotContainsString('<th', $html);
    }

    public function test_xss_in_data_values_is_escaped(): void
    {
        $html = $this->renderMail(data: ['unit' => '<img onerror=alert(1) src=x>']);

        $this->assertStringNotContainsString('<img onerror=', $html);
        $this->assertTrue(
            str_contains($html, '&lt;img') || str_contains($html, '&amp;lt;img'),
            'XSS payload must be entity-escaped in rendered output'
        );
    }

    public function test_xss_in_recipient_name_is_escaped(): void
    {
        $html = $this->renderMail(recipientName: '<script>alert("name")</script>');

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertTrue(
            str_contains($html, '&lt;script&gt;') || str_contains($html, '&amp;lt;script&amp;gt;'),
            'Script tag in recipient name must be entity-escaped in rendered output'
        );
    }

    public function test_action_button_rejects_javascript_url(): void
    {
        $html = $this->renderMail(data: [
            'action_url' => 'javascript:alert(1)',
            'action_text' => 'Click Me',
        ]);

        $this->assertStringNotContainsString('class="button', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_pipe_in_data_value_does_not_break_table(): void
    {
        $html = $this->renderMail(data: ['note' => 'A|B']);

        preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $html, $matches);
        $found = false;
        foreach ($matches[1] as $cell) {
            $decoded = html_entity_decode(strip_tags($cell));
            if (str_contains($decoded, 'A|B')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Pipe should not split into extra columns');
    }

    public function test_action_url_without_action_text_omits_button(): void
    {
        $html = $this->renderMail(data: [
            'action_url' => 'https://example.com/pay',
        ]);

        $this->assertStringNotContainsString('class="button', $html);
    }
}
