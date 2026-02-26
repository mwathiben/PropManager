<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Mail\Mailable;
use Laravel\Dusk\Browser;
use Tests\Support\Contracts\MailCapturePort;
use Tests\Support\Exceptions\MailpitConnectionException;
use Tests\Support\MailpitClient;

trait InteractsWithMailpit
{
    use OverridesMailConfig;

    protected MailCapturePort $mailpit;

    protected function setUpMailpit(): void
    {
        $this->mailpit = new MailpitClient;

        try {
            $this->mailpit->deleteAll();
        } catch (MailpitConnectionException $e) {
            $this->markTestSkipped('Mailpit is not running at localhost:8025 — '.$e->getMessage());
        }

        $this->setUpMailConfig();
    }

    protected function assertEmailSentTo(string $email, ?string $subjectContains = null): void
    {
        $message = $this->mailpit->waitForMessage($email);

        $this->assertNotNull($message, "Expected email to {$email} but none was captured by Mailpit.");

        if ($subjectContains !== null) {
            $this->assertStringContainsString(
                $subjectContains,
                $message['Subject'] ?? '',
                "Email subject does not contain '{$subjectContains}'.",
            );
        }
    }

    protected function assertEmailCount(int $expected): void
    {
        $messages = $this->mailpit->messages();

        $this->assertCount(
            $expected,
            $messages,
            "Expected {$expected} email(s) but Mailpit captured ".count($messages).'.',
        );
    }

    protected function getLatestEmailHtml(): string
    {
        $message = $this->mailpit->getLatestMessage();
        $this->assertNotNull($message, 'No emails captured by Mailpit.');

        $html = $this->mailpit->getMessageHtml($message['ID']);

        if ($html !== '') {
            return $html;
        }

        return $message['Snippet'] ?? '';
    }

    protected function getLatestEmailLinks(): array
    {
        $message = $this->mailpit->getLatestMessage();
        $this->assertNotNull($message, 'No emails captured by Mailpit.');

        return $this->mailpit->getMessageLinks($message['ID']);
    }

    protected function screenshotEmail(Browser $browser, string $name): void
    {
        $html = $this->getLatestEmailHtml();
        $this->screenshotHtml($browser, $html, $name);
    }

    protected function screenshotMailableRender(Browser $browser, Mailable $mailable, string $name): void
    {
        $html = $mailable->render();
        $this->screenshotHtml($browser, $html, $name);
    }

    private function screenshotHtml(Browser $browser, string $html, string $name): void
    {
        $screenshotDir = base_path('e2e-screenshots/emails');

        if (! is_dir($screenshotDir)) {
            mkdir($screenshotDir, 0755, true);
        }

        $dataUri = 'data:text/html;base64,'.base64_encode($html);
        $browser->driver->navigate()->to($dataUri);
        $browser->pause(500);
        $browser->driver->takeScreenshot($screenshotDir.DIRECTORY_SEPARATOR.$name.'.png');
    }
}
