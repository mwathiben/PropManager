<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Exceptions\DataIntegrityException;
use App\Exceptions\DocumensoException;
use App\Jobs\Concerns\TracksFailures;
use App\Models\AgreementSignature;
use App\Models\ManagementAgreement;
use App\Services\Agreements\AgreementApplicator;
use App\Services\Documenso\DocumensoService;
use App\Services\Documenso\SealedAgreementArtifacts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Slice-2 PR-2.4b: seal the Documenso evidence onto a signature and activate the
 * agreement. Triggered by the DOCUMENT_COMPLETED webhook (the source of truth).
 *
 * Downloads the certificate-sealed PDF + signing certificate, stores them on a
 * private disk with the PDF hash for tamper-evidence, then mirrors the 2.3c
 * transition (signature -> Signed, agreement -> Signed -> AgreementApplicator
 * activates + LOCKS the fee). Idempotent and concurrency-safe: the activation
 * re-reads under a row lock, so a replayed or duplicate webhook never re-applies
 * a fee. A signed-but-unactivated agreement is a money-integrity event, so a
 * terminal failure is surfaced loudly via failed() — never swallowed.
 */
class FinalizeDocumensoSignatureJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TracksFailures;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(
        public int $signatureId,
        public int $documentId,
        public ?string $envelopeId,
    ) {
        // Only enqueue after the surrounding DB transaction commits (the
        // verify-OTP envelope-creation path in 2.4b-ii dispatches inside one).
        $this->afterCommit();
    }

    public function handle(DocumensoService $documenso, AgreementApplicator $applicator): void
    {
        $signature = AgreementSignature::with('agreement')->find($this->signatureId);
        if ($signature === null || $signature->status === AgreementSignatureStatus::Signed) {
            return;
        }
        if ($signature->agreement === null) {
            return;
        }

        $artifacts = $this->fetchAndStoreArtifacts($documenso);

        try {
            $this->sealAndActivate($applicator, $artifacts);
        } catch (DataIntegrityException $e) {
            // A missing/malformed fee clause is terminal — retrying cannot fix it.
            // Fail fast straight to failed() so the money-integrity alert fires now
            // instead of after burning every retry on a guaranteed failure.
            $this->fail($e);
        }
    }

    private function fetchAndStoreArtifacts(DocumensoService $documenso): SealedAgreementArtifacts
    {
        $disk = (string) config('documenso.storage_disk', 'private');

        // The sealed PDF is THE integrity artifact: a download failure must stay
        // loud (throws DocumensoException -> the job retries), never silent.
        $pdf = $documenso->downloadSignedPdf($this->documentId);
        $pdfPath = "agreements/signed/{$this->signatureId}.pdf";
        Storage::disk($disk)->put($pdfPath, $pdf);

        // The certificate is supplementary evidence — a fetch failure must NOT
        // block activating the fee. Degrade to "no certificate" and warn for backfill.
        $certificatePath = null;
        if ($this->envelopeId !== null && $this->envelopeId !== '') {
            try {
                $certificatePath = "agreements/signed/{$this->signatureId}-certificate.pdf";
                Storage::disk($disk)->put($certificatePath, $documenso->downloadCertificate($this->envelopeId));
            } catch (DocumensoException $e) {
                $certificatePath = null;
                Log::warning('Documenso certificate download failed; sealing PDF + activating without it', [
                    'signature_id' => $this->signatureId,
                    'envelope_id' => $this->envelopeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new SealedAgreementArtifacts($pdfPath, $certificatePath, hash('sha256', $pdf));
    }

    private function sealAndActivate(AgreementApplicator $applicator, SealedAgreementArtifacts $artifacts): void
    {
        DB::transaction(function () use ($applicator, $artifacts): void {
            // Re-read under a row lock: the database queue driver runs concurrent
            // workers and a webhook can deliver more than once, so two finalizers
            // may race. The loser sees Signed and no-ops — the fee activates once.
            $signature = AgreementSignature::whereKey($this->signatureId)->lockForUpdate()->first();
            if ($signature === null || $signature->status === AgreementSignatureStatus::Signed) {
                return;
            }

            $agreement = ManagementAgreement::whereKey($signature->management_agreement_id)->lockForUpdate()->first();
            if ($agreement === null) {
                return;
            }

            $signature->forceFill([
                'status' => AgreementSignatureStatus::Signed,
                'signing_method' => 'documenso',
                'documenso_status' => 'completed',
                'documenso_envelope_id' => $this->envelopeId,
                'documenso_completed_at' => now(),
                'signed_at' => $signature->signed_at ?? now(),
                'signed_pdf_path' => $artifacts->signedPdfPath,
                'certificate_path' => $artifacts->certificatePath,
                'sealed_pdf_sha256' => $artifacts->sha256,
            ])->save();

            if ($agreement->status !== AgreementStatus::Active) {
                $agreement->forceFill([
                    'status' => AgreementStatus::Signed,
                    'signed_at' => $agreement->signed_at ?? now(),
                ])->save();

                $applicator->activate($agreement);
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        // The owner completed signing in Documenso (fire-once webhook) but the fee
        // never applied — a money-integrity event, not a routine job failure. A
        // failed_jobs row is not a notification, so raise a critical alert.
        Log::critical('Documenso finalize permanently failed; agreement signed but fee NOT activated — needs manual review', [
            'signature_id' => $this->signatureId,
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);

        $this->recordJobFailure($exception);
    }
}
