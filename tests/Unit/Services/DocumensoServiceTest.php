<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\DocumensoException;
use App\Services\Documenso\DocumensoService;
use App\Services\Documenso\DocumensoSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumensoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'documenso.base_url' => 'https://docs.example.test',
            'documenso.api_token' => 'tok_secret_123',
            'documenso.webhook_secret' => 'whk_secret',
            'documenso.api_version' => 'v1',
            'documenso.timeout' => 5,
            'documenso.retry_attempts' => 1,
            'documenso.retry_delay_ms' => 1,
        ]);
    }

    private function fakeCreateFlow(): void
    {
        Http::fake([
            'docs.example.test/api/v1/documents' => Http::response([
                'uploadUrl' => 'https://s3.example.test/upload?sig=abc',
                'documentId' => 42,
                'recipients' => [[
                    'recipientId' => 7,
                    'token' => 'recipient-token-xyz',
                    'signingUrl' => 'https://docs.example.test/sign/recipient-token-xyz',
                    'role' => 'SIGNER',
                ]],
            ], 200),
            'docs.example.test/api/v1/documents/*/send' => Http::response([], 200),
            's3.example.test/*' => Http::response('', 200),
        ]);
    }

    public function test_create_signing_envelope_creates_uploads_and_sends(): void
    {
        $this->fakeCreateFlow();

        $envelope = app(DocumensoService::class)->createSigningEnvelope(
            '%PDF-1.4 fake bytes',
            new DocumensoSigner('Jane Owner', 'jane@example.com'),
            'Management Agreement',
            'agreement-sig-99',
        );

        $this->assertSame(42, $envelope->documentId);
        $this->assertSame('recipient-token-xyz', $envelope->recipientToken);
        $this->assertSame('https://docs.example.test/sign/recipient-token-xyz', $envelope->signingUrl);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://docs.example.test/api/v1/documents'
            && $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Bearer tok_secret_123')
            && $r['externalId'] === 'agreement-sig-99'
            && $r['recipients'][0]['email'] === 'jane@example.com'
            && $r['recipients'][0]['role'] === 'SIGNER');

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->url() === 'https://s3.example.test/upload?sig=abc'
            && $r->body() === '%PDF-1.4 fake bytes');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/documents/42/send')
            && $r->method() === 'POST'
            && $r['sendEmail'] === false);
    }

    public function test_create_throws_when_not_configured(): void
    {
        config(['documenso.base_url' => '', 'documenso.api_token' => null]);

        $this->expectException(DocumensoException::class);

        app(DocumensoService::class)->createSigningEnvelope('pdf', new DocumensoSigner('n', 'e@e.test'), 't', 'ext');
    }

    public function test_create_throws_on_api_error(): void
    {
        Http::fake(['docs.example.test/api/v1/documents' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(DocumensoException::class);

        app(DocumensoService::class)->createSigningEnvelope('pdf', new DocumensoSigner('n', 'e@e.test'), 't', 'ext');
    }

    public function test_download_signed_pdf_returns_bytes(): void
    {
        Http::fake([
            'docs.example.test/api/v2-beta/document/42/download*' => Http::response('SEALED-PDF-BYTES', 200),
        ]);

        $bytes = app(DocumensoService::class)->downloadSignedPdf(42);

        $this->assertSame('SEALED-PDF-BYTES', $bytes);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/v2-beta/document/42/download')
            && $r->hasHeader('Authorization', 'Bearer tok_secret_123'));
    }

    public function test_download_certificate_returns_bytes(): void
    {
        Http::fake([
            'docs.example.test/api/v2-beta/envelope/env_abc/certificate/pdf' => Http::response('CERT-BYTES', 200),
        ]);

        $bytes = app(DocumensoService::class)->downloadCertificate('env_abc');

        $this->assertSame('CERT-BYTES', $bytes);
    }

    public function test_download_signed_pdf_throws_on_error(): void
    {
        Http::fake(['docs.example.test/api/v2-beta/document/*/download*' => Http::response('', 404)]);

        $this->expectException(DocumensoException::class);

        app(DocumensoService::class)->downloadSignedPdf(42);
    }

    public function test_embed_signing_url_builds_embed_path(): void
    {
        $this->assertSame(
            'https://docs.example.test/embed/sign/recipient-token-xyz',
            app(DocumensoService::class)->embedSigningUrl('recipient-token-xyz'),
        );
    }
}
