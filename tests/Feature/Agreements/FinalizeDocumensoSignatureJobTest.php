<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementSignatureMethod;
use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Enums\ManagementFeeType;
use App\Exceptions\DataIntegrityException;
use App\Jobs\FinalizeDocumensoSignatureJob;
use App\Models\AgreementSignature;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Services\Agreements\AgreementApplicator;
use App\Services\Documenso\DocumensoService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
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
        $this->assertSame(AgreementSignatureMethod::Documenso, $signature->signing_method);
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

    public function test_artifact_cleanup_failure_never_masks_the_money_integrity_failure(): void
    {
        // No fee clause -> AgreementApplicator fails closed with a terminal
        // DataIntegrityException. The best-effort artifact cleanup then runs; if the
        // disk throws (misconfigured/unavailable disk, S3 outage), that MUST be
        // swallowed so it can never replace the terminal exception. The job still
        // fails loudly via fail() so failed() raises the money-integrity alert
        // (the failed()->critical leg is covered by test_permanent_failure_*).
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

        // Artifact storage (put) succeeds, but cleanup (delete) blows up.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andReturnTrue();
        $disk->shouldReceive('delete')->andThrow(new \RuntimeException('disk unavailable during cleanup'));
        Storage::shouldReceive('disk')->with('private')->andReturn($disk);

        Log::spy();

        // Inject a queue job so $this->fail() is observable: the terminal exception
        // must reach it, never be supplanted by the cleanup RuntimeException.
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('fail')->once()->with(Mockery::type(DataIntegrityException::class));

        $job = new FinalizeDocumensoSignatureJob($signature->id, 42, 'env_abc');
        $job->setJob($queueJob);
        $job->handle(app(DocumensoService::class), app(AgreementApplicator::class));

        // The swallowed cleanup error is surfaced as a warning with enough context to backfill.
        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($signature): bool {
                return str_contains($message, 'cleanup')
                    && ($context['signature_id'] ?? null) === $signature->id
                    && array_key_exists('paths', $context)
                    && array_key_exists('error', $context);
            })
            ->once();
    }

    public function test_permanent_failure_raises_a_critical_alert(): void
    {
        Log::spy();

        (new FinalizeDocumensoSignatureJob(123, 42, 'env_abc'))->failed(new \RuntimeException('boom'));

        Log::shouldHaveReceived('critical')->once();
    }

    public function test_does_not_activate_a_terminated_agreement(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = PropertyOwner::factory()->create([
            'landlord_id' => $manager->id,
            'management_fee_type' => ManagementFeeType::None,
        ]);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Terminated,
        ]);
        $signature = AgreementSignature::factory()->create([
            'management_agreement_id' => $agreement->id,
            'landlord_id' => $manager->id,
            'status' => AgreementSignatureStatus::Pending,
            'documenso_document_id' => 42,
        ]);
        $this->fakeDownloads();

        $this->runJob($signature->id);

        // Refused: no resurrection, no download, no fee.
        $this->assertSame(AgreementSignatureStatus::Pending, $signature->fresh()->status);
        $this->assertSame(AgreementStatus::Terminated, $agreement->fresh()->status);
        $this->assertNull($owner->fresh()->management_fee_locked_at);
        Http::assertNothingSent();
    }

    public function test_already_active_agreement_records_signature_without_reactivating(): void
    {
        [$signature, $agreement, $owner] = $this->makeSignable(42);
        $agreement->forceFill(['status' => AgreementStatus::Active])->save();
        $this->fakeDownloads();

        $this->runJob($signature->id);

        // Signature evidence is recorded, but the fee is NOT (re)activated/locked.
        $this->assertSame(AgreementSignatureStatus::Signed, $signature->fresh()->status);
        $this->assertSame(ManagementFeeType::None, $owner->fresh()->management_fee_type);
        $this->assertNull($owner->fresh()->management_fee_locked_at);
        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
    }

    public function test_declined_signature_is_a_noop(): void
    {
        [$signature, $agreement, $owner] = $this->makeSignable(42);
        $signature->forceFill(['status' => AgreementSignatureStatus::Declined])->save();
        $this->fakeDownloads();

        $this->runJob($signature->id);

        $this->assertSame(AgreementSignatureStatus::Declined, $signature->fresh()->status);
        $this->assertSame(AgreementStatus::Sent, $agreement->fresh()->status);
        $this->assertNull($owner->fresh()->management_fee_locked_at);
        Http::assertNothingSent();
    }

    public function test_in_house_signature_reads_back_as_method_enum(): void
    {
        [$signature] = $this->makeSignable(77);

        // The in-house path never sets signing_method — it relies on the DB default,
        // which the enum cast must hydrate back to InHouse.
        $this->assertSame(AgreementSignatureMethod::InHouse, $signature->fresh()->signing_method);
    }
}
