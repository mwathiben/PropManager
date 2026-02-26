<?php

namespace Tests\Browser;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\InteractsWithMailpit;

class EmailTestSmokeTest extends DuskTestCase
{
    use InteractsWithMailpit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
    }

    public function test_mailpit_captures_email_sent_via_smtp(): void
    {
        Mail::raw('Smoke test email body', function ($message) {
            $message->to('smoke-test@example.com')
                ->subject('E2E Smoke Test');
        });

        $this->assertEmailSentTo('smoke-test@example.com', 'Smoke Test');
        $this->assertEmailCount(1);
    }

    public function test_screenshot_email_captures_to_file(): void
    {
        Mail::to('screenshot@example.com')
            ->send(new SmokeTestMailable);

        $this->assertEmailSentTo('screenshot@example.com', 'Smoke Test Email');

        $html = $this->getLatestEmailHtml();
        $this->assertStringContainsString('Screenshot Test Content', $html);

        $this->browse(function (Browser $browser) {
            $this->screenshotEmail($browser, 'smoke-test-email');
        });

        $this->assertFileExists(base_path('e2e-screenshots/emails/smoke-test-email.png'));
    }

    public function test_get_latest_email_links_extracts_hrefs(): void
    {
        Mail::to('links@example.com')
            ->send(new SmokeTestMailable);

        $this->assertEmailSentTo('links@example.com');

        $links = $this->getLatestEmailLinks();
        $this->assertContains('https://example.com/test-link', $links);
    }
}

class SmokeTestMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Smoke Test Email');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<html><body>'
            .'<h1>Screenshot Test Content</h1>'
            .'<p>This is a smoke test email with HTML body.</p>'
            .'<a href="https://example.com/test-link">Test Link</a>'
            .'</body></html>');
    }
}
