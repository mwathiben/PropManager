<?php

declare(strict_types=1);

namespace App\Services\Documenso;

use App\Exceptions\DocumensoException;
use App\Exceptions\Resilience\CircuitOpenException;
use App\Traits\LogsExternalRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Slice-2 PR-2.4b: client for the self-hosted Documenso e-signature service —
 * the PKCS#12 certificate-backed signed-PDF integrity layer for management
 * agreements. Platform-level (config-driven, shared across tenants).
 *
 * The create→upload→send flow targets API v1 (the self-hosted instance must run
 * with S3 upload transport); the signed-PDF + certificate downloads use v2-beta
 * (returns bytes directly, storage-agnostic). Every failure surfaces as a
 * DocumensoException so the signing path can fall back to the in-house flow.
 */
class DocumensoService
{
    use LogsExternalRequests;

    /**
     * Render an agreement PDF into a Documenso envelope with the owner as the
     * sole SIGNER. `sendEmail: false` — the owner signs through our embedded
     * iframe, not Documenso's own email, so we suppress Documenso's notice.
     */
    public function createSigningEnvelope(
        string $pdfBytes,
        DocumensoSigner $signer,
        string $title,
        string $externalId,
    ): DocumensoEnvelope {
        $this->assertConfigured();

        $create = $this->guardedRequest('/api/v1/documents', fn () => $this->client()
            ->post($this->url('/api/v1/documents'), [
                'title' => $title,
                'externalId' => $externalId,
                'recipients' => [[
                    'name' => $signer->name,
                    'email' => $signer->email,
                    'role' => 'SIGNER',
                ]],
            ]));
        $this->assertSuccessful($create, 'create document', '/api/v1/documents');

        ['uploadUrl' => $uploadUrl, 'documentId' => $documentId, 'token' => $token, 'signingUrl' => $signingUrl]
            = $this->parseCreateResponse($create);

        $upload = $this->guardedRequest('s3-upload', fn () => Http::timeout($this->timeout())
            ->retry($this->retryAttempts(), $this->retryDelayMs(), fn ($e) => $e instanceof ConnectionException, throw: false)
            ->withBody($pdfBytes, 'application/pdf')
            ->put($uploadUrl));
        $this->assertSuccessful($upload, 'upload pdf', 's3-upload');

        $send = $this->guardedRequest('/send', fn () => $this->client()
            ->post($this->url("/api/v1/documents/{$documentId}/send"), ['sendEmail' => false]));
        $this->assertSuccessful($send, 'send document', '/send');

        return new DocumensoEnvelope($documentId, $token, $signingUrl);
    }

    /**
     * @return array{uploadUrl: string, documentId: int, token: string, signingUrl: string}
     */
    private function parseCreateResponse(Response $create): array
    {
        $uploadUrl = (string) $create->json('uploadUrl');
        $documentId = (int) $create->json('documentId');
        $token = (string) $create->json('recipients.0.token');
        $signingUrl = (string) $create->json('recipients.0.signingUrl');

        if ($uploadUrl === '' || $documentId === 0 || $token === '' || $signingUrl === '') {
            $this->fail('create document (malformed response)', '/api/v1/documents', $create->status(), $create->body());
        }

        return ['uploadUrl' => $uploadUrl, 'documentId' => $documentId, 'token' => $token, 'signingUrl' => $signingUrl];
    }

    private function assertSuccessful(Response $response, string $action, string $endpoint): void
    {
        if (! $response->successful()) {
            $this->fail($action, $endpoint, $response->status(), $response->body());
        }
    }

    /** The certificate-sealed PDF bytes (v2-beta returns the bytes inline). */
    public function downloadSignedPdf(int $documentId): string
    {
        $this->assertConfigured();

        $response = $this->guardedRequest('/document/download', fn () => $this->client()
            ->get($this->url("/api/v2-beta/document/{$documentId}/download"), ['version' => 'signed']));
        $this->assertSuccessful($response, 'download signed pdf', '/document/download');

        return $response->body();
    }

    /** The signing certificate PDF (identity assertions), available once COMPLETED. */
    public function downloadCertificate(string $envelopeId): string
    {
        $this->assertConfigured();

        $response = $this->guardedRequest('/certificate', fn () => $this->client()
            ->get($this->url("/api/v2-beta/envelope/{$envelopeId}/certificate/pdf")));
        $this->assertSuccessful($response, 'download certificate', '/certificate');

        return $response->body();
    }

    /** The frameable embedded-signing URL; the Vue page appends the #base64 config hash. */
    public function embedSigningUrl(string $token): string
    {
        return $this->baseUrl()."/embed/sign/{$token}";
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) $this->token())
            ->timeout($this->timeout())
            ->retry(
                $this->retryAttempts(),
                $this->retryDelayMs(),
                fn ($e) => $e instanceof ConnectionException,
                throw: false,
            );
    }

    private function guardedRequest(string $endpoint, callable $call): Response
    {
        try {
            return $this->timedHttpRequest('documenso', $endpoint, $call);
        } catch (ConnectionException|CircuitOpenException $e) {
            // CircuitOpenException is thrown by the (opt-in) breaker inside
            // timedHttpRequest when OPEN; it is not a ConnectionException, so it
            // must be caught explicitly or it would leak past the signing path.
            Log::error('Documenso request unreachable', [
                'endpoint' => $endpoint,
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            throw new DocumensoException("Documenso unreachable at {$endpoint}.", previous: $e);
        }
    }

    private function fail(string $action, string $endpoint, int $status, string $body): never
    {
        Log::error('Documenso '.$action.' failed', [
            'endpoint' => $endpoint,
            'status' => $status,
            'body' => $this->redactSecrets($body),
        ]);

        throw new DocumensoException("Documenso {$action} failed with status {$status}.");
    }

    /**
     * Strip URL query strings from a connection-error message before logging.
     * cURL errors embed the failing URL, and the S3 upload hop's presigned URL
     * carries a short-lived signature that must never land in logs.
     */
    private function sanitizeError(string $message): string
    {
        return preg_replace('#(https?://[^\s?]+)\?\S*#i', '$1?[REDACTED]', $message) ?? $message;
    }

    private function assertConfigured(): void
    {
        if ($this->baseUrl() === '' || in_array($this->token(), [null, '', false], true)) {
            throw new DocumensoException('Documenso is not configured (DOCUMENSO_BASE_URL / DOCUMENSO_API_TOKEN).');
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('documenso.base_url', ''), '/');
    }

    private function url(string $path): string
    {
        return $this->baseUrl().$path;
    }

    private function token(): ?string
    {
        return config('documenso.api_token');
    }

    private function timeout(): int
    {
        return (int) config('documenso.timeout', 30);
    }

    private function retryAttempts(): int
    {
        return max(1, (int) config('documenso.retry_attempts', 3));
    }

    private function retryDelayMs(): int
    {
        return max(0, (int) config('documenso.retry_delay_ms', 200));
    }

    private function redactSecrets(string $body): string
    {
        $truncated = substr($body, 0, 500);

        $patterns = [
            '/("?(?:secret_key|authorization|Bearer|password|token|api_key|access_token)"?\s*[:=]\s*)"[^"]*"/i' => '$1"[REDACTED]"',
            '/(Bearer\s+)[A-Za-z0-9._-]+/i' => '$1[REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $truncated) ?? $truncated;
    }
}
