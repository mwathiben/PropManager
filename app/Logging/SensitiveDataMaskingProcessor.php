<?php

declare(strict_types=1);

namespace App\Logging;

use App\Services\KenyaDpaService;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Phase-13 DPA-6: Monolog processor that masks sensitive personal
 * data found in log context. Before this processor, a logged request
 * payload containing national_id, bank details, or any other DPA
 * Section 44 sensitive-data category shipped to log files unmasked.
 *
 * The processor walks the log record's context recursively and
 * masks values whose key matches a PII key. The matching set
 * combines:
 *   - SECRET_KEYS: tokens, secrets, passwords — fully redacted
 *   - PII_KEYS:    identifying personal data — masked to show only
 *                  the first / last character (so logs are still
 *                  diagnosable but the value is not recoverable)
 *   - KenyaDpaService::SENSITIVE_DATA_CATEGORIES — Kenya DPA
 *                  Section 44 categories, fully redacted
 *
 * The processor is registered globally via config/logging.php so
 * every channel benefits. DomainException's sanitizeForLogging
 * remains the per-exception path (defence in depth — even if the
 * processor misses a key, the exception's own masking catches it
 * first when the exception is reported).
 */
class SensitiveDataMaskingProcessor implements ProcessorInterface
{
    private const SECRET_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'api_secret',
        'webhook_secret',
        'access_token',
        'refresh_token',
        'authorization',
        'private_key',
        'private',
        'session_id',
        'csrf_token',
        '_token',
    ];

    private const PII_KEYS = [
        'email',
        'phone',
        'mobile',
        'mobile_number',
        'account_number',
        'bank_account',
        'id_number',
        'national_id',
        'tax_pin',
        'kra_pin',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;

        if (! empty($context)) {
            $context = $this->maskRecursive($context);
        }

        return $record->with(context: $context);
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private function maskRecursive(array $data, int $depth = 0): array
    {
        // Defensive: cap recursion at 6 levels. Deeper-than-6 nesting
        // in a log context is almost always a mistake; refusing to
        // recurse prevents pathological CPU on a circular-ref-ish
        // payload.
        if ($depth > 6) {
            return $data;
        }

        $masked = [];
        foreach ($data as $key => $value) {
            $masked[$key] = $this->maskEntry($key, $value, $depth);
        }

        return $masked;
    }

    private function maskEntry(int|string $key, mixed $value, int $depth): mixed
    {
        if (is_string($key)) {
            $lowerKey = strtolower($key);

            if ($this->keyIsSecret($lowerKey) || $this->keyIsSensitiveCategory($lowerKey)) {
                return '[REDACTED]';
            }

            if ($this->keyIsPii($lowerKey)) {
                return $this->maskScalar($value);
            }
        }

        if (is_array($value)) {
            return $this->maskRecursive($value, $depth + 1);
        }

        return $value;
    }

    private function keyIsSecret(string $lowerKey): bool
    {
        foreach (self::SECRET_KEYS as $needle) {
            if (str_contains($lowerKey, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function keyIsPii(string $lowerKey): bool
    {
        foreach (self::PII_KEYS as $needle) {
            if (str_contains($lowerKey, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function keyIsSensitiveCategory(string $lowerKey): bool
    {
        return in_array($lowerKey, KenyaDpaService::SENSITIVE_DATA_CATEGORIES, true);
    }

    private function maskScalar(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '[MASKED]';
        }

        $string = (string) $value;
        $length = strlen($string);
        if ($length <= 2) {
            return '[MASKED]';
        }
        if ($length <= 6) {
            return $string[0].str_repeat('*', $length - 2).$string[$length - 1];
        }

        return substr($string, 0, 2).str_repeat('*', $length - 4).substr($string, -2);
    }
}
