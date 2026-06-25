<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Slice-2 PR-2.3c: lifecycle of an owner's signing invitation. pending (token
 * emailed, awaiting the owner) -> signed (OTP verified + evidence recorded) or
 * declined (owner refused / the manager revoked the request).
 */
enum AgreementSignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';

    /**
     * Terminal states a completion webhook must NOT re-process. This is the money
     * idempotency guard: a signed OR declined signature is never (re)activated, so
     * a late/duplicate Documenso completion cannot resurrect a refused signature.
     */
    public function isTerminal(): bool
    {
        return $this === self::Signed || $this === self::Declined;
    }
}
