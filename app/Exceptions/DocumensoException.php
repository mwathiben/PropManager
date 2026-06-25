<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the self-hosted Documenso service is unconfigured, unreachable,
 * or returns a non-success response. Callers on the signing path treat this as
 * "Documenso unavailable" and fall back to the in-house OTP click-sign (2.3c),
 * so a Documenso outage never blocks an owner from signing.
 */
class DocumensoException extends RuntimeException {}
