<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MilestoneRecorded;
use App\Models\OnboardingMilestone;
use App\Models\User;
use App\Services\Growth\ReferralAttributionService;

/**
 * Phase-34 GROWTH-REFERRAL-2: flip pending referrals to attributed
 * when the referred landlord completes the first_invoice milestone.
 *
 * Why first_invoice (not signed_up): a landlord who signs up then
 * disappears never reaches the value moment. We attribute on the
 * action that proves the referred user is real + active. Industry
 * standard for B2B SaaS referral programs.
 *
 * Auto-discovered by Laravel 11 event resolver via handle() signature.
 */
class AttributeReferralOnMilestone
{
    public function __construct(
        private readonly ReferralAttributionService $service,
    ) {}

    public function handle(MilestoneRecorded $event): void
    {
        if ((string) $event->milestone->milestone !== OnboardingMilestone::FIRST_INVOICE) {
            return;
        }

        $landlord = User::find($event->milestone->landlord_id);
        if (! $landlord) {
            return;
        }

        $this->service->attribute($landlord);
    }
}
