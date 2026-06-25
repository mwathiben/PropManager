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
}
