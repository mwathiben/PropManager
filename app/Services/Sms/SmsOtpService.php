<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Phase-45 EMERGENCY-CONTACT-SMS-1: generate, send, and verify 6-digit
 * OTPs over SMS. Provider-agnostic: takes an SmsDriver in the
 * constructor; the container binds Stub by default and Africa's Talking
 * when sms.driver === 'africastalking'.
 *
 * The OTP itself is cached for OTP_TTL_MINUTES (10 minutes) at
 * `otp:contact:{contact_id}` so server restart doesn't lose in-flight
 * codes (cache is shared across workers).
 */
class SmsOtpService
{
    public const OTP_TTL_MINUTES = 10;

    public function __construct(private readonly SmsDriver $driver)
    {
    }

    /**
     * Generate a 6-digit code, cache it keyed by $cacheKey, and dispatch
     * an SMS via the configured driver. Returns the provider reference.
     */
    public function generateAndSend(string $phone, string $cacheKey, string $messageTemplate = 'Your verification code is :code'): string
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Cache::put($cacheKey, $code, now()->addMinutes(self::OTP_TTL_MINUTES));

        $message = str_replace(':code', $code, $messageTemplate);

        return $this->driver->send($phone, $message);
    }

    /**
     * Verify the supplied code against the cached value. Consumes the
     * cache entry on success so the same code cannot be reused. Throws
     * ValidationException with field 'code' on mismatch / expiry.
     */
    public function verify(string $cacheKey, string $code): void
    {
        $cached = Cache::get($cacheKey);
        if ($cached === null) {
            throw ValidationException::withMessages(['code' => 'OTP expired. Please request a new code.']);
        }
        if (! hash_equals((string) $cached, $code)) {
            throw ValidationException::withMessages(['code' => 'Invalid verification code.']);
        }

        Cache::forget($cacheKey);
    }
}
