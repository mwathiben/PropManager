<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Enums\ManagementFeeType;
use App\Jobs\FinalizeDocumensoSignatureJob;
use App\Models\AgreementSignature;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Services\Agreements\AgreementApplicator;
use App\Services\Documenso\DocumensoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Slice-2 PR-2.4b: finalizing seals the Documenso artifacts onto the evidence
 * and runs the same activation seam as the in-house path — so what the PM bills
 * is exactly the signed, certificate-sealed agreement. The money-correctness +
 * idempotency are exercised directly.
 */
class FinalizeDocumensoSignatureJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'documenso.base_url' => 'https://docs.example.test',
            'documenso.api_token' => 'tok',
            'documenso.storage_disk' => 'private',
        ]);
        Storage::fake('private');
    }

    /** @return array{0: AgreementSignature, 1: ManagementAgreement, 2: PropertyOwner} */
    private function makeSignable(int $documentId = 42): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = PropertyOwner::factory()->create([
            'landlord_id' => $manager->id,
            'management_fee_type' => ManagementFeeType::None,
        ]);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Sent,
        ]);
        $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->managementFee()->create()->id,
            'params' => ['type' => 'percentage', 'value' => 8, 'base' => 'collected'],
            'position' => 0,
        ]);
        $signature = AgreementSignature::factory()->create([
            'management_agreement_id' => $agreement->id,
            'landlord_id' => $manager->id,
            'status' => AgreementSignatureStatus::Pending,
            'documenso_document_id' => $documentId,
        ]);

        return [$signature, $agreement, $owner];
    }

    private function fakeDownloads(): void
    {
        Http::fake([
            'docs.example.test/api/v2-beta/document/*/download*' => Http::response('SEALED-PDF', 200),
            'docs.example.test/api/v2-beta/envelope/*/certificate/pdf' => Http::response('CERT-PDF', 200),
        ]);
    }

    private function runJob(int $signatureId, int $documentId = 42, ?string $envelopeId = 'env_abc'): void
    {
        (new FinalizeDocumensoSignatureJob($signatureId, $documentId, $envelopeId))
            ->handle(app(DocumensoService::class), app(AgreementApplicator::class));
    }

    public function test_finalize_seals_evidence_and_activates_fee(): void
    {
        [$signature, $agreement, $owner] = $this->makeSignable(42);
        $this->fakeDownloads();

        $this->runJob($signature->id);

        $signature->refresh();
        $this->assertSame(AgreementSignatureStatus::Signed, $signature->status);
        $this->assertSame('documenso', $signature->signing_method);
        $this->assertSame(hash('sha256', 'SEALED-PDF'), $signature->sealed_pdf_sha256);
        $this->assertNotNull($signature->documenso_completed_at);
        $this->assertSame('env_abc', $signature->documenso_envelope_id);

        Storage::disk('private')->assertExists($signature->signed_pdf_path);
        Storage::disk('private')->assertExists($signature->certificate_path);

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);

        $owner->refresh();
        $this->assertSame(ManagementFeeType::Percentage, $owner->management_fee_type);
        $this->assertEquals(8.0, (float) $owner->management_fee_value);
        $this->assertNotNull($owner->management_fee_locked_at, 'fee must be locked after activation');
    }

    public function test_finalize_is_idempotent(): void
    {
        [$signature, $agreement, $owner] = $this->makeSignable(42);
        $this->fakeDownloads();

        $this->runJob($signature->id);
        $lockedAt = $owner->fresh()->management_fee_locked_at;

        $this->runJob($signature->id);

        $this->assertEquals($lockedAt, $owner->fresh()->management_fee_locked_at);
        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
    }

    public function test_unknown_signature_is_a_noop(): void
    {
        $this->fakeDownloads();

        $this->runJob(999_999);

        Http::assertNothingSent();
    }

    public function test_certificate_download_failure_still_seals_and_activates(): void
    {
        [$signature, $agreement, $owner] = $this->makeSignable(42);
        Http::fake([
            'docs.example.test/api/v2-beta/document/*/download*' => Http::response('SEALED-PDF', 200),
            'docs.example.test/api/v2-beta/envelope/*/certificate/pdf' => Http::response('', 500),
        ]);

        $this->runJob($signature->id);

        $signature->refresh();
        $this->assertSame(AgreementSignatureStatus::Signed, $signature->status);
        $this->assertNull($signature->certificate_path, 'certificate is supplementary — its failure must not block');
        $this->assertNotNull($signature->signed_pdf_path);
        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertNotNull($owner->fresh()->management_fee_locked_at);
    }

    public function test_terminal_fee_clause_error_leaves_signature_unsigned(): void
    {
        // An agreement with NO fee clause: AgreementApplicator fails closed
        // (DataIntegrityException). The signing must roll back — never a
        // half-signed signature whose fee was never applied.
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Sent,
        ]);
        $signature = AgreementSignature::factory()->create([
            'management_agreement_id' => $agreement->id,
            'landlord_id' => $manager->id,
            'status' => AgreementSignatureStatus::Pending,
            'documenso_document_id' => 42,
        ]);
        $this->fakeDownloads();

        $this->runJob($signature->id);

        $this->assertSame(AgreementSignatureStatus::Pending, $signature->fresh()->status);
        $this->assertSame(AgreementStatus::Sent, $agreement->fresh()->status);
    }

    public function test_permanent_failure_raises_a_critical_alert(): void
    {
        Log::spy();

        (new FinalizeDocumensoSignatureJob(123, 42, 'env_abc'))->failed(new \RuntimeException('boom'));

        Log::shouldHaveReceived('critical')->once();
    }
}
