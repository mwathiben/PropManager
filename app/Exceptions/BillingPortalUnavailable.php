<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Phase-60 BILLING-PORTAL-1: thrown by StripeService when the
 * landlord can't be sent to the Stripe-hosted Customer Portal —
 * either no StripeCustomer mapping exists (onboarding incomplete)
 * or the Stripe SDK call failed. Message is a translation key so
 * the controller can render a localised flash.
 */
class BillingPortalUnavailable extends Exception
{
    public function __construct(string $translationKey, ?Throwable $previous = null)
    {
        parent::__construct($translationKey, previous: $previous);
    }

    public function translationKey(): string
    {
        return $this->getMessage();
    }
}
