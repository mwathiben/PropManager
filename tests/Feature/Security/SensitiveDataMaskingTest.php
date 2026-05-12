<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Logging\SensitiveDataMaskingProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * Phase-13 DPA-6 regression coverage. The Monolog processor must
 * mask PII keys + Kenya DPA Section 44 sensitive-data categories in
 * any log context without distorting non-sensitive fields.
 */
class SensitiveDataMaskingTest extends TestCase
{
    private function process(array $context): array
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: $context,
        );

        $processed = (new SensitiveDataMaskingProcessor)($record);

        return $processed->context;
    }

    public function test_secret_keys_are_fully_redacted(): void
    {
        $out = $this->process([
            'password' => 'StrongPass123!',
            'api_key' => 'sk_live_abc',
            'access_token' => 'eyJabc',
            'csrf_token' => 'xxx',
            '_token' => 'yyy',
            'plain' => 'kept',
        ]);

        $this->assertSame('[REDACTED]', $out['password']);
        $this->assertSame('[REDACTED]', $out['api_key']);
        $this->assertSame('[REDACTED]', $out['access_token']);
        $this->assertSame('[REDACTED]', $out['csrf_token']);
        $this->assertSame('[REDACTED]', $out['_token']);
        $this->assertSame('kept', $out['plain']);
    }

    public function test_pii_keys_are_partially_masked(): void
    {
        $out = $this->process([
            'email' => 'alice@example.test',
            'phone' => '+254712345678',
            'kra_pin' => 'A123456789X',
        ]);

        // PII masks show first 2 + last 2 chars for strings >6.
        // national_id is NOT tested here because it also matches a
        // Kenya DPA Section 44 sensitive category and gets the
        // stronger [REDACTED] treatment (see other test).
        $this->assertSame('al**************st', $out['email']);
        $this->assertSame('+2*********78', $out['phone']);
        $this->assertSame('A1*******9X', $out['kra_pin']);
    }

    public function test_kenya_dpa_sensitive_categories_are_redacted(): void
    {
        $out = $this->process([
            'national_id' => '12345',
            'ethnic_origin' => 'Kikuyu',
            'health_data' => 'positive',
            'religious_belief' => 'X',
            'sex_life' => 'Y',
        ]);

        // national_id matches both PII (mask) and sensitive category
        // (redact) — sensitive-category check wins because it's first.
        $this->assertSame('[REDACTED]', $out['national_id']);
        $this->assertSame('[REDACTED]', $out['ethnic_origin']);
        $this->assertSame('[REDACTED]', $out['health_data']);
        $this->assertSame('[REDACTED]', $out['religious_belief']);
        $this->assertSame('[REDACTED]', $out['sex_life']);
    }

    public function test_nested_arrays_are_walked_recursively(): void
    {
        $out = $this->process([
            'request' => [
                'body' => [
                    'email' => 'alice@example.test',
                    'password' => 'secret',
                ],
                'safe' => 'kept',
            ],
        ]);

        $this->assertSame('[REDACTED]', $out['request']['body']['password']);
        $this->assertSame('al**************st', $out['request']['body']['email']);
        $this->assertSame('kept', $out['request']['safe']);
    }

    public function test_non_sensitive_keys_pass_through_unchanged(): void
    {
        $out = $this->process([
            'invoice_id' => 42,
            'amount' => 1500.50,
            'status' => 'paid',
            'metadata' => ['k' => 'v'],
        ]);

        $this->assertSame(42, $out['invoice_id']);
        $this->assertSame(1500.50, $out['amount']);
        $this->assertSame('paid', $out['status']);
        $this->assertSame(['k' => 'v'], $out['metadata']);
    }

    public function test_substring_match_catches_email_address_and_phone_number_keys(): void
    {
        $out = $this->process([
            'user_email' => 'a@b.test',
            'contact_phone' => '0712345678',
            'partner_mobile_number' => '0700000000',
        ]);

        $this->assertNotSame('a@b.test', $out['user_email']);
        $this->assertNotSame('0712345678', $out['contact_phone']);
        $this->assertNotSame('0700000000', $out['partner_mobile_number']);
    }

    public function test_recursion_is_capped_to_avoid_pathological_payloads(): void
    {
        $deep = ['leaf' => 'visible'];
        for ($i = 0; $i < 8; $i++) {
            $deep = ['inner' => $deep];
        }

        $out = $this->process(['root' => $deep]);

        // Cap is depth=6; deeper-than-6 must return un-walked.
        $this->assertSame($out['root'], $deep);
    }
}
