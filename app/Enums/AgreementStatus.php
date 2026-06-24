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
}
