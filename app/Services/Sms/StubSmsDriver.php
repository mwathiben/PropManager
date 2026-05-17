<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Log;

/**
 * Phase-45 EMERGENCY-CONTACT-SMS-1: default SMS driver. Logs the message
 * + intended recipient instead of dispatching to a real provider. CI and
 * dev environments stay on this driver so no test ever accidentally
 * burns SMS credits or contacts a real phone.
 */
class StubSmsDriver implements SmsDriver
{
    /** @var list<array{phone: string, message: string, ref: string}> */
    public static array $sent = [];

    public function send(string $phone, string $message): string
    {
        $ref = 'stub-'.bin2hex(random_bytes(6));
        self::$sent[] = ['phone' => $phone, 'message' => $message, 'ref' => $ref];

        Log::info('[sms-stub] would send', [
            'phone' => $phone,
            'message' => $message,
            'ref' => $ref,
        ]);

        return $ref;
    }
}
