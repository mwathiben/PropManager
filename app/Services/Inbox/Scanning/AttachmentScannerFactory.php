<?php

declare(strict_types=1);

namespace App\Services\Inbox\Scanning;

/**
 * Phase-67 ATTACHMENT-SCAN-1: single authority that builds the scanner
 * for config('inbox.scan.driver'). Bound to AttachmentScannerInterface in
 * AppServiceProvider; tests bind a FakeScanner instance directly.
 */
class AttachmentScannerFactory
{
    public static function make(): AttachmentScannerInterface
    {
        $driver = (string) config('inbox.scan.driver', 'null');

        return match ($driver) {
            'clamav' => new ClamavScanner(
                socket: config('inbox.scan.socket'),
                host: (string) config('inbox.scan.host', '127.0.0.1'),
                port: (int) config('inbox.scan.port', 3310),
                timeout: (int) config('inbox.scan.timeout', 10),
            ),
            'fake' => new FakeScanner,
            default => new NullScanner,
        };
    }
}
