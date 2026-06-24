<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Slice-2: lifecycle of a management agreement. draft → sent (to owner) → signed
 * (owner assented) → active (AgreementApplicator wrote+locked the governed
 * config, PR 2.3) → amending (a governed change proposed, awaiting re-assent) →
 * terminated.
 */
enum AgreementStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Signed = 'signed';
    case Active = 'active';
    case Amending = 'amending';
    case Terminated = 'terminated';

    /**
     * Once signed, the rendered snapshot + governed config are immutable — they
     * change only via an amendment the counterparty re-signs. The drift-lock
     * guard (PR 2.3) keys on this, and recomputeRenderedBody() refuses to run.
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::Signed, self::Active, self::Amending], true);
    }

    /** The legal lifecycle transitions — enforced when the apply/amend flow lands (PR 2.3). */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Sent, self::Terminated],
            self::Sent => [self::Signed, self::Terminated],
            self::Signed => [self::Active, self::Terminated],
            self::Active => [self::Amending, self::Terminated],
            self::Amending => [self::Active, self::Terminated],
            self::Terminated => [],
        }, true);
    }
}
