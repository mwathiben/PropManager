<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
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
use Illuminate\Support\Facades\Storage;

/**
 * Slice-2 PR-2.4b: seal the Documenso evidence onto a signature and activate the
 * agreement. Triggered by the DOCUMENT_COMPLETED webhook (the source of truth).
 *
 * Downloads the certificate-sealed PDF + signing certificate, stores them on a
 * private disk with the PDF hash for tamper-evidence, then mirrors the 2.3c
 * transition (signature -> Signed, agreement -> Signed -> AgreementApplicator
 * activates + LOCKS the fee). Idempotent: a replayed webhook on an
 * already-signed signature is a no-op, so a fee is never re-applied.
 */
class FinalizeDocumensoSignatureJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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

        $agreement = $signature->agreement;
        if ($agreement === null) {
            return;
        }

        $artifacts = $this->fetchAndStoreArtifacts($documenso);

        $this->sealAndActivate($signature, $agreement, $applicator, $artifacts);
    }

    private function fetchAndStoreArtifacts(DocumensoService $documenso): SealedAgreementArtifacts
    {
        $disk = (string) config('documenso.storage_disk', 'private');

        $pdf = $documenso->downloadSignedPdf($this->documentId);
        $pdfPath = "agreements/signed/{$this->signatureId}.pdf";
        Storage::disk($disk)->put($pdfPath, $pdf);

        $certificatePath = null;
        if ($this->envelopeId !== null && $this->envelopeId !== '') {
            $certificatePath = "agreements/signed/{$this->signatureId}-certificate.pdf";
            Storage::disk($disk)->put($certificatePath, $documenso->downloadCertificate($this->envelopeId));
        }

        return new SealedAgreementArtifacts($pdfPath, $certificatePath, hash('sha256', $pdf));
    }

    private function sealAndActivate(
        AgreementSignature $signature,
        ManagementAgreement $agreement,
        AgreementApplicator $applicator,
        SealedAgreementArtifacts $artifacts,
    ): void {
        DB::transaction(function () use ($signature, $agreement, $applicator, $artifacts): void {
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
}
