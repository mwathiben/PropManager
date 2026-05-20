<?php

declare(strict_types=1);

namespace App\Services\Inbox\Scanning;

/**
 * Phase-67 ATTACHMENT-SCAN-1: the default driver for environments without
 * ClamAV (local/test). Treats every file as clean — it is a no-op gate,
 * not a security control. Production must point inbox.scan.driver at the
 * clamav driver.
 */
class NullScanner implements AttachmentScannerInterface
{
    public function scan(string $absolutePath): ScanResult
    {
        return ScanResult::clean();
    }
}
