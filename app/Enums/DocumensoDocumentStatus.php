<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The Documenso envelope's lifecycle as PropManager tracks it: pending (envelope
 * created, awaiting the owner's embedded signature) -> completed (DOCUMENT_COMPLETED
 * webhook: sealed PDF + certificate retrieved) or failed. PR 2.4b-ii populates
 * Pending at envelope creation; 2.4b-i sets Completed on the webhook.
 */
enum DocumensoDocumentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
