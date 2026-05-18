<?php

declare(strict_types=1);

namespace App\Services\Growth;

/**
 * Phase-56 FUNNEL-SANKEY-1: canonical funnel stage names.
 *
 * product_events.event_name = 'funnel.'.$stage->value when emitted via
 * FunnelEventEmitter. The Sankey rollup queries on the exact 'funnel.*'
 * prefix; adding new stages requires adding to BOTH this enum AND the
 * FunnelRollupService stage order.
 */
enum FunnelStage: string
{
    case SIGNUP = 'signup';
    case ONBOARDING_COMPLETE = 'onboarding_complete';
    case FIRST_PAYMENT = 'first_payment';
    case RETAINED_60D = 'retained_60d';

    public function eventName(): string
    {
        return 'funnel.'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::SIGNUP => 'Signup',
            self::ONBOARDING_COMPLETE => 'Onboarding complete',
            self::FIRST_PAYMENT => 'First payment',
            self::RETAINED_60D => 'Retained 60d',
        };
    }

    /**
     * @return array<int, self> Stages in funnel order.
     */
    public static function ordered(): array
    {
        return [
            self::SIGNUP,
            self::ONBOARDING_COMPLETE,
            self::FIRST_PAYMENT,
            self::RETAINED_60D,
        ];
    }
}
