<?php

declare(strict_types=1);

namespace App\Services\Inbox\Scanning;

use Illuminate\Support\Facades\Log;

/**
 * Phase-67 ATTACHMENT-SCAN-1: ClamAV driver speaking the clamd INSTREAM
 * protocol directly over a socket — no extra Composer dependency. Streams
 * the file to clamd and parses the one-line verdict. Any transport
 * failure is swallowed into ScanResult::error so the caller's
 * fail-open/fail-closed policy decides; this method never throws.
 *
 * Config (config/inbox.php scan.*): socket (unix path, preferred) OR
 * host+port, plus a connect/read timeout.
 */
class ClamavScanner implements AttachmentScannerInterface
{
    private const CHUNK = 8192;

    public function __construct(
        private readonly ?string $socket,
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeout,
    ) {}

    public function scan(string $absolutePath): ScanResult
    {
        if (! is_readable($absolutePath)) {
            return ScanResult::error('unreadable');
        }

        $remote = $this->socket !== null && $this->socket !== ''
            ? 'unix://'.$this->socket
            : 'tcp://'.$this->host.':'.$this->port;

        $errno = 0;
        $errstr = '';
        $connection = @stream_socket_client($remote, $errno, $errstr, $this->timeout);

        if ($connection === false) {
            Log::warning('clamav connect failed', ['remote' => $remote, 'error' => $errstr]);

            return ScanResult::error('connect_failed');
        }

        try {
            stream_set_timeout($connection, $this->timeout);
            fwrite($connection, "zINSTREAM\0");

            $handle = fopen($absolutePath, 'rb');
            if ($handle === false) {
                return ScanResult::error('open_failed');
            }

            while (! feof($handle)) {
                $chunk = fread($handle, self::CHUNK);
                if ($chunk === '' || $chunk === false) {
                    break;
                }
                fwrite($connection, pack('N', strlen($chunk)).$chunk);
            }
            fclose($handle);

            // Zero-length chunk terminates the stream.
            fwrite($connection, pack('N', 0));

            $response = trim((string) fgets($connection));
        } catch (\Throwable $e) {
            Log::warning('clamav scan error', ['error' => $e->getMessage()]);

            return ScanResult::error('scan_exception');
        } finally {
            fclose($connection);
        }

        // clamd replies "stream: OK" or "stream: <Signature> FOUND".
        if (str_contains($response, 'FOUND')) {
            $signature = trim(str_replace(['stream:', 'FOUND'], '', $response));

            return ScanResult::infected($signature !== '' ? $signature : 'unknown');
        }

        if (str_ends_with($response, 'OK')) {
            return ScanResult::clean();
        }

        // Anything else is a clamd-level error reply, e.g. "INSTREAM size
        // limit exceeded" if a future upload cap is raised above clamd's
        // StreamMaxLength (today the Form Request caps attachments at 5 MB,
        // well under the clamd default). Treat as scanner error so the
        // fail-open/fail-closed policy — not this driver — decides.
        return ScanResult::error($response !== '' ? $response : 'empty_response');
    }
}
