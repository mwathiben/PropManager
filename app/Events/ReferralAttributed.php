<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-34 GROWTH-REFERRAL-2: fires when a pending referral flips
 * to 'attributed' (referred landlord completed the first_invoice
 * milestone). Listeners can dispatch reward emails, credit a free
 * month, or notify the referrer.
 */
class ReferralAttributed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Referral $referral)
    {
    }
}
