<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-82 DOC-REMINDERS-1: fired by documents:scan-expiring when a renewable,
 * current document is within its reminder window. NotifyOnDocumentExpiry fans
 * out to the landlord (and the tenant when the document belongs to their
 * lease/KYC).
 */
class DocumentExpiryApproaching
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Document $document,
        public readonly int $daysRemaining,
    ) {}
}
