<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AgreementSignatureMethod;
use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Enums\DocumensoDocumentStatus;
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
        if ($signature === null || $signature->status->isTerminal()) {
            return;
        }

        $agreement = $signature->agreement;
        if ($agreement === null) {
            return;
        }

        // Refuse to resurrect a non-activatable agreement (terminated/draft/amending):
        // don't even download artifacts. Surface the conflict for manual review.
        if (! $this->isActivatableState($agreement->status)) {
            Log::warning('Documenso completion for a non-activatable agreement; not activating', [
                'signature_id' => $this->signatureId,
                'agreement_id' => $agreement->id,
                'status' => $agreement->status->value,
            ]);

            return;
        }

        $artifacts = $this->fetchAndStoreArtifacts($documenso);

        try {
            $this->sealAndActivate($applicator, $artifacts);
        } catch (DataIntegrityException $e) {
            // A missing/malformed fee clause is terminal — retrying cannot fix it.
            // Drop the orphaned artifacts and fail fast so the money-integrity alert
            // fires now instead of after burning every retry on a guaranteed failure.
            $this->discardArtifacts($artifacts);
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
            // may race. The loser sees a terminal signature and no-ops.
            $signature = AgreementSignature::whereKey($this->signatureId)->lockForUpdate()->first();
            if ($signature === null || $signature->status->isTerminal()) {
                return;
            }

            // Re-check the lifecycle under the lock — the agreement may have been
            // terminated between the pre-check and acquiring the lock (TOCTOU).
            $agreement = ManagementAgreement::whereKey($signature->management_agreement_id)->lockForUpdate()->first();
            if ($agreement === null || ! $this->isActivatableState($agreement->status)) {
                return;
            }

            $this->recordSignature($signature, $artifacts);

            // Activate only from a signable state. If already Active, a prior
            // delivery activated it — the signature evidence is recorded above,
            // but the fee is NOT re-locked.
            if ($agreement->status !== AgreementStatus::Active) {
                $this->markAgreementSigned($agreement);
                $applicator->activate($agreement);
            }
        });
    }

    private function recordSignature(AgreementSignature $signature, SealedAgreementArtifacts $artifacts): void
    {
        $signature->forceFill([
            'status' => AgreementSignatureStatus::Signed,
            'signing_method' => AgreementSignatureMethod::Documenso,
            'documenso_status' => DocumensoDocumentStatus::Completed,
            'documenso_envelope_id' => $this->envelopeId,
            'documenso_completed_at' => now(),
            'signed_at' => $signature->signed_at ?? now(),
            'signed_pdf_path' => $artifacts->signedPdfPath,
            'certificate_path' => $artifacts->certificatePath,
            'sealed_pdf_sha256' => $artifacts->sha256,
        ])->save();
    }

    private function markAgreementSigned(ManagementAgreement $agreement): void
    {
        $agreement->forceFill([
            'status' => AgreementStatus::Signed,
            'signed_at' => $agreement->signed_at ?? now(),
        ])->save();
    }

    /**
     * Sent/Signed are signable; Active means a prior delivery already activated
     * (a no-op seal). Draft/Amending/Terminated must never be force-activated.
     */
    private function isActivatableState(AgreementStatus $status): bool
    {
        return in_array($status, [AgreementStatus::Sent, AgreementStatus::Signed, AgreementStatus::Active], true);
    }

    private function discardArtifacts(SealedAgreementArtifacts $artifacts): void
    {
        try {
            $disk = (string) config('documenso.storage_disk', 'private');
            Storage::disk($disk)->delete(array_values(array_filter([
                $artifacts->signedPdfPath,
                $artifacts->certificatePath,
            ])));
        } catch (Throwable) {
            // Best-effort cleanup — it must never mask the terminal failure we are
            // failing the job on (that exception carries the money-integrity alert).
        }
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
