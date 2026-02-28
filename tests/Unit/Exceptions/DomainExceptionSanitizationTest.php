<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\Integration\PaystackException;
use PHPUnit\Framework\TestCase;

class DomainExceptionSanitizationTest extends TestCase
{
    public function test_sanitizes_password_key(): void
    {
        $exception = new PaystackException(
            'Test error',
            'TEST_ERROR',
            ['password' => 'secret123']
        );

        $sanitized = $this->invokeSanitizeForLogging($exception, $exception->getContext());

        $this->assertSame('[REDACTED]', $sanitized['password']);
    }

    public function test_sanitizes_temporary_password_camel_case(): void
    {
        $exception = new PaystackException(
            'Test error',
            'TEST_ERROR',
            ['temporaryPassword' => 'abc123xyz']
        );

        $sanitized = $this->invokeSanitizeForLogging($exception, $exception->getContext());

        $this->assertSame('[REDACTED]', $sanitized['temporaryPassword']);
    }

    public function test_sanitizes_temporary_password_snake_case(): void
    {
        $exception = new PaystackException(
            'Test error',
            'TEST_ERROR',
            ['temporary_password' => 'abc123xyz']
        );

        $sanitized = $this->invokeSanitizeForLogging($exception, $exception->getContext());

        $this->assertSame('[REDACTED]', $sanitized['temporary_password']);
    }

    public function test_strips_password_variants_from_public_context(): void
    {
        $exception = new PaystackException(
            'Test error',
            'TEST_ERROR',
            [
                'temporaryPassword' => 'abc123xyz',
                'password' => 'secret',
                'temporary_password' => 'temp',
                'reference' => 'REF-001',
            ]
        );

        $publicContext = $exception->getPublicContext();

        $this->assertArrayNotHasKey('temporaryPassword', $publicContext);
        $this->assertArrayNotHasKey('password', $publicContext);
        $this->assertArrayNotHasKey('temporary_password', $publicContext);
        $this->assertSame('REF-001', $publicContext['reference']);
    }

    private function invokeSanitizeForLogging(object $exception, array $context): array
    {
        $method = new \ReflectionMethod($exception, 'sanitizeForLogging');

        return $method->invoke($exception, $context);
    }
}
