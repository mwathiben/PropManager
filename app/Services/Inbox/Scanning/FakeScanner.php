<?php

declare(strict_types=1);

namespace App\Services\Inbox\Scanning;

/**
 * Phase-67 ATTACHMENT-SCAN-1: deterministic scanner for tests + staging.
 * Flags any file whose contents contain the EICAR antivirus test
 * signature (the industry-standard harmless test pattern), so the
 * infected path can be exercised without a real virus or a clamd server.
 */
class FakeScanner implements AttachmentScannerInterface
{
    public const EICAR_MARKER = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE';

    public function scan(string $absolutePath): ScanResult
    {
        if (! is_readable($absolutePath)) {
            return ScanResult::error('unreadable');
        }

        $contents = (string) file_get_contents($absolutePath);

        return str_contains($contents, self::EICAR_MARKER)
            ? ScanResult::infected('Eicar-Test-Signature')
            : ScanResult::clean();
    }
}
