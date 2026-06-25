<?php

declare(strict_types=1);

namespace App\Services\Documenso;

/**
 * The result of creating a Documenso signing envelope: the document id we use
 * for downloads + webhook correlation, and the recipient token that drives the
 * owner's embedded signing iframe (/embed/sign/{token}).
 */
final readonly class DocumensoEnvelope
{
    public function __construct(
        public int $documentId,
        public string $recipientToken,
        public string $signingUrl,
    ) {}
}
