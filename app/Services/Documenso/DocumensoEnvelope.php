<?php

declare(strict_types=1);

namespace App\Services\Documenso;

/**
 * The result of creating a Documenso signing envelope: the document id we use
 * for downloads + webhook correlation, and the recipient token that drives the
 * owner's embedded signing iframe (/embed/sign/{token}).
 *
 * Note: $signingUrl is Documenso's raw /sign/{token} API URL, NOT the embed URL.
 * Build the iframe src from the token via DocumensoService::embedSigningUrl().
 */
final readonly class DocumensoEnvelope
{
    public function __construct(
        public int $documentId,
        public string $recipientToken,
        public string $signingUrl,
    ) {
        if ($documentId <= 0 || $recipientToken === '') {
            throw new \InvalidArgumentException('A Documenso envelope requires a positive document id and a non-empty recipient token.');
        }
    }
}
