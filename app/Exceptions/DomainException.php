<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for domain-specific exceptions.
 *
 * IMPORTANT: Do NOT put sensitive data (PII) into the $context array.
 * Fields like email, phone, password, SSN, tokens, or full account numbers
 * should never be passed in context. If you need to include identifiers,
 * use masked/obfuscated versions or internal non-sensitive IDs only.
 *
 * Use $publicContext for data that is safe to expose in API responses.
 */
abstract class DomainException extends Exception
{
    protected string $errorCode;

    /**
     * Internal context for logging (will be sanitized before logging).
     */
    protected array $context = [];

    /**
     * Public context safe to expose in API responses.
     * Subclasses should populate this with non-sensitive data only.
     */
    protected array $publicContext = [];

    protected int $statusCode = 400;

    /**
     * Keys that should be stripped or obfuscated from context before logging/rendering.
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'temporary_password',
        'secret',
        'ssn',
        'token',
        'api_key',
        'apikey',
        'credentials',
        'credit_card',
        'card_number',
        'cvv',
        'pin',
        'private_key',
    ];

    /**
     * Keys containing PII that should be masked (show partial value).
     */
    protected const PII_KEYS = [
        'email',
        'phone',
        'mobile',
        'account_number',
        'bank_account',
        'id_number',
        'national_id',
    ];

    /**
     * Keys that should be completely removed from public responses.
     */
    protected const INTERNAL_KEYS = [
        'db_id',
        'debug',
        'internal_id',
        'internal_ref',
        'internal_code',
        'stack_trace',
    ];

    public function __construct(
        string $message,
        string $errorCode,
        array $context = [],
        int $statusCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->statusCode = $statusCode;
        $this->publicContext = $this->sanitizeForPublic($context);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the public-safe context for API responses.
     */
    public function getPublicContext(): array
    {
        return $this->publicContext;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set public context explicitly (for subclasses that need fine-grained control).
     */
    protected function setPublicContext(array $context): void
    {
        $this->publicContext = $context;
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $this->errorCode,
                'message' => $this->getMessage(),
                'context' => $this->publicContext,
            ], $this->statusCode);
        }

        return null;
    }

    public function report(): void
    {
        Log::error($this->getMessage(), [
            'error_code' => $this->errorCode,
            'exception' => static::class,
            'context' => $this->sanitizeForLogging($this->context),
            'trace' => $this->getTraceAsString(),
        ]);
    }

    /**
     * Sanitize context for logging - masks PII and removes secrets.
     */
    protected function sanitizeForLogging(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower($key);

            // Completely remove sensitive secrets
            if ($this->matchesKeyPattern($lowerKey, self::SENSITIVE_KEYS)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            // Mask PII fields
            if ($this->matchesKeyPattern($lowerKey, self::PII_KEYS)) {
                $sanitized[$key] = $this->maskValue($value);

                continue;
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeForLogging($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Sanitize context for public API responses - more restrictive than logging.
     */
    protected function sanitizeForPublic(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower($key);

            // Remove sensitive keys entirely
            if ($this->matchesKeyPattern($lowerKey, self::SENSITIVE_KEYS)) {
                continue;
            }

            // Remove PII from public responses
            if ($this->matchesKeyPattern($lowerKey, self::PII_KEYS)) {
                continue;
            }

            // Remove internal keys
            if ($this->matchesKeyPattern($lowerKey, self::INTERNAL_KEYS)) {
                continue;
            }

            // Check for internal_* pattern
            if (str_starts_with($lowerKey, 'internal')) {
                continue;
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeForPublic($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Check if a key matches any of the patterns.
     */
    protected function matchesKeyPattern(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($key === $pattern || str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask a value, showing only the last few characters.
     */
    protected function maskValue(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '[MASKED]';
        }

        $value = (string) $value;
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        // Show last 4 characters
        return str_repeat('*', $length - 4).substr($value, -4);
    }

    /**
     * Helper to mask an account/phone number (for use by subclasses).
     */
    public static function maskAccountNumber(?string $number): ?string
    {
        if ($number === null || strlen($number) <= 4) {
            return $number ? str_repeat('*', strlen($number)) : null;
        }

        return str_repeat('*', strlen($number) - 4).substr($number, -4);
    }
}
