<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Enums\DocumensoDocumentStatus;
use App\Enums\ManagementFeeType;
use App\Models\AgreementSignature;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Services\Agreements\AgreementPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Slice-2 PR-2.4b-ii: after the OTP identity gate, the owner is routed through
 * Documenso's embedded signing when Documenso is reachable; otherwise the in-house
 * assent (2.3c) is the fallback. The signature stays Pending on the Documenso path
 * (the DOCUMENT_COMPLETED webhook activates it), so the money is NOT applied here.
 */
class AgreementSignEmbedPathTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: ManagementAgreement, 1: AgreementSignature, 2: PropertyOwner} */
    private function signable(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = PropertyOwner::factory()->forLandlord($manager)->create([
            'email' => 'owner@example.com',
            'phone' => '254712345678',
            'management_fee_type' => ManagementFeeType::None,
        ]);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Sent,
            'rendered_body' => 'The agreement text',
            'content_hash' => hash('sha256', 'The agreement text'),
            'sent_at' => now(),
        ]);
        $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->managementFee()->create()->id,
            'params' => ['type' => 'percentage', 'value' => 8, 'base' => 'collected'],
            'position' => 0,
        ]);
        $signature = AgreementSignature::factory()->for($agreement, 'agreement')->create([
            'landlord_id' => $manager->id,
            'signer_phone' => '254712345678',
            'signer_email' => 'owner@example.com',
            'status' => AgreementSignatureStatus::Pending,
        ]);

        return [$agreement, $signature, $owner];
    }

    private function configureDocumenso(): void
    {
        config([
            'documenso.base_url' => 'https://docs.example.test',
            'documenso.api_token' => 'tok',
            'documenso.retry_attempts' => 1,
            'documenso.retry_delay_ms' => 1,
        ]);
    }

    private function fakeCreateFlow(): void
    {
        Http::fake([
            'docs.example.test/api/v1/documents' => Http::response([
                'uploadUrl' => 'https://s3.example.test/up?sig=x',
                'documentId' => 42,
                'recipients' => [[
                    'token' => 'recipient-token-xyz',
                    'signingUrl' => 'https://docs.example.test/sign/recipient-token-xyz',
                    'role' => 'SIGNER',
                ]],
            ], 200),
            'docs.example.test/api/v1/documents/*/send' => Http::response([], 200),
            's3.example.test/*' => Http::response('', 200),
        ]);
    }

    private function seedOtp(string $token): string
    {
        $this->post(route('agreements.sign.otp', $token))->assertRedirect();

        return (string) Cache::get("otp:agreement-sign:{$token}");
    }

    public function test_documenso_path_creates_envelope_returns_embed_and_does_not_activate(): void
    {
        [$agreement, $signature] = $this->signable();
        $this->configureDocumenso();
        $this->fakeCreateFlow();
        $code = $this->seedOtp($signature->token);

        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertOk()->assertInertia(fn ($page) => $page
            ->component('Agreements/Sign')
            ->where('embed.token', 'recipient-token-xyz')
            ->where('embed.baseUrl', 'https://docs.example.test'));

        $sig = $signature->fresh();
        $this->assertSame(AgreementSignatureStatus::Pending, $sig->status, 'signature stays Pending until the webhook');
        $this->assertSame(42, $sig->documenso_document_id);
        $this->assertSame(DocumensoDocumentStatus::Pending, $sig->documenso_status);
        $this->assertNotNull($sig->otp_verified_at);

        // The webhook — not this request — activates the fee.
        $this->assertSame(AgreementStatus::Sent, $agreement->fresh()->status);
    }

    public function test_falls_back_to_in_house_when_documenso_unreachable(): void
    {
        [$agreement, $signature, $owner] = $this->signable();
        $this->configureDocumenso();
        Http::fake(['docs.example.test/api/v1/documents' => Http::response(['e' => 'boom'], 500)]);
        $code = $this->seedOtp($signature->token);

        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertRedirect();

        // Documenso failed -> in-house assent activated the fee, signer never blocked.
        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertSame(AgreementSignatureStatus::Signed, $signature->fresh()->status);
        $this->assertNotNull($owner->fresh()->management_fee_locked_at);
    }

    public function test_retry_reuses_the_existing_envelope_without_creating_a_duplicate(): void
    {
        [$agreement, $signature] = $this->signable();
        $signature->update([
            'documenso_document_id' => 99,
            'documenso_recipient_token' => 'existing-token',
            'documenso_status' => DocumensoDocumentStatus::Pending,
        ]);
        $this->configureDocumenso();
        $this->fakeCreateFlow();
        $code = $this->seedOtp($signature->token);

        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertOk()->assertInertia(fn ($page) => $page->where('embed.token', 'existing-token'));

        // No new envelope created.
        Http::assertNotSent(fn (Request $r) => $r->url() === 'https://docs.example.test/api/v1/documents');
    }

    public function test_malformed_signer_email_falls_back_to_in_house_without_500(): void
    {
        [$agreement, $signature, $owner] = $this->signable();
        // Present-but-invalid email: must short-circuit to in-house, never reach
        // DocumensoSigner (which would throw InvalidArgumentException -> 500).
        $signature->update(['signer_email' => 'owner@']);
        $this->configureDocumenso();
        $this->fakeCreateFlow();
        $code = $this->seedOtp($signature->token);

        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertRedirect();

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertSame(AgreementSignatureStatus::Signed, $signature->fresh()->status);
        $this->assertNull($signature->fresh()->documenso_document_id);
        $this->assertNotNull($owner->fresh()->management_fee_locked_at);
        Http::assertNotSent(fn (Request $r) => $r->url() === 'https://docs.example.test/api/v1/documents');
    }

    public function test_pdf_render_failure_falls_back_to_in_house_without_500(): void
    {
        [$agreement, $signature, $owner] = $this->signable();
        $this->configureDocumenso();
        $this->fakeCreateFlow();
        // A PDF render error (e.g. DomPDF blowing up) must degrade to the in-house assent,
        // never 500 the owner. prepareDocumensoEnvelope catches \Throwable BEFORE the
        // envelope HTTP call, so no document is created and the fee still activates in-house.
        $this->mock(AgreementPdfRenderer::class)
            ->shouldReceive('render')
            ->andThrow(new \RuntimeException('dompdf exploded'));
        $code = $this->seedOtp($signature->token);

        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertRedirect();

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertSame(AgreementSignatureStatus::Signed, $signature->fresh()->status);
        $this->assertNull($signature->fresh()->documenso_document_id);
        $this->assertNotNull($owner->fresh()->management_fee_locked_at);
        Http::assertNotSent(fn (Request $r) => $r->url() === 'https://docs.example.test/api/v1/documents');
    }
}
