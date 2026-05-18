<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Phase-60 COUPONS-2: thrown by CouponService::redeem on validation
 * failure. The message is a translation key so callers can render
 * a localised flash without leaking implementation details.
 */
class CouponInvalidException extends Exception
{
    public function __construct(string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public function translationKey(): string
    {
        return $this->getMessage();
    }
}
