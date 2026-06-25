<?php

declare(strict_types=1);

namespace App\Services\Documenso;

/**
 * The stored Documenso integrity artifacts for a signed agreement: the path to
 * the certificate-sealed PDF, the path to the signing certificate (if any), and
 * the SHA-256 of the sealed PDF for tamper-evidence.
 */
final readonly class SealedAgreementArtifacts
{
    public function __construct(
        public string $signedPdfPath,
        public ?string $certificatePath,
        public string $sha256,
    ) {}
}
