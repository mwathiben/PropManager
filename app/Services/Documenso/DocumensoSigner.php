<?php

declare(strict_types=1);

namespace App\Services\Documenso;

/**
 * The single recipient of a management-agreement envelope — the property owner.
 * Groups name + email so DocumensoService::createSigningEnvelope stays within a
 * sane parameter count.
 */
final readonly class DocumensoSigner
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
