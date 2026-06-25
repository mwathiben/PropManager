<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an owner's signature was captured: the in-house OTP click-assent (PR 2.3c)
 * or Documenso's certificate-sealed embedded signing (PR 2.4b). A backed enum so
 * the value can never drift to a typo'd string (the enum-vs-string bug class).
 */
enum AgreementSignatureMethod: string
{
    case InHouse = 'in_house';
    case Documenso = 'documenso';
}
