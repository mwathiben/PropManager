<?php

declare(strict_types=1);

namespace App\Services\Inbox\Scanning;

/**
 * Phase-67 ATTACHMENT-SCAN-1: pluggable malware scanner for inbox
 * attachments (SmsDriver-style ports-and-adapters). Implementations must
 * never throw — a scanner failure is reported as ScanResult::error so the
 * caller decides fail-open vs fail-closed.
 */
interface AttachmentScannerInterface
{
    public function scan(string $absolutePath): ScanResult;
}
