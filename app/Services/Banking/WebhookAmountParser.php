<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\ValueObjects\Money;
use InvalidArgumentException;

/**
 * Phase-17 MONEY-6: strict validation of attacker-supplied amounts at
 * the bank-webhook ingress boundary.
 *
 * Pre-Phase-17 the three banking services (KCB / Equity / Co-op) each
 * did `amount: (float) $payload['Amount']`. PHP's `(float)` cast is
 * lossy + permissive:
 *   - 'twelve thousand' → 0.0 (silent zero!)
 *   - '12345.678x9' → 12345.68 (silent truncation)
 *   - '1e3' → 1000.0 (silently parsed scientific notation)
 *
 * This helper rejects all three: caller catches the exception and
 * lands the payload in the WebhookDeadLetter for operator review.
 * The Phase-10B encrypted-webhook-payload guarantee means the value
 * we receive IS the value the bank intended — but a bank-side bug
 * (or, in pathological scenarios, an end-to-end-encryption gap)
 * could still produce malformed values; rejecting them here is
 * defence-in-depth.
 */
final class WebhookAmountParser
{
    public static function parse(mixed $rawAmount): Money
    {
        if ($rawAmount === null || $rawAmount === '') {
            throw new InvalidArgumentException('Webhook Amount must not be empty');
        }

        // Reject obviously-non-numeric strings before bcmath can be
        // tricked by them.
        $asString = is_scalar($rawAmount) ? (string) $rawAmount : '';
        if (! is_numeric($asString)) {
            throw new InvalidArgumentException("Webhook Amount '{$asString}' is not numeric");
        }

        // Money::fromString rejects scientific notation explicitly.
        return Money::fromString($asString);
    }
}
