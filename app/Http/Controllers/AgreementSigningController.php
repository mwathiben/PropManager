<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AgreementSignatureMethod;
use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Enums\DocumensoDocumentStatus;
use App\Exceptions\DocumensoException;
use App\Http\Requests\SignAgreementRequest;
use App\Models\AgreementSignature;
use App\Models\ManagementAgreement;
use App\Services\Agreements\AgreementApplicator;
use App\Services\Agreements\AgreementPdfRenderer;
use App\Services\Documenso\DocumensoEnvelope;
use App\Services\Documenso\DocumensoService;
use App\Services\Documenso\DocumensoSigner;
use App\Services\Sms\SmsOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Slice-2 PR-2.3c: the owner's public, token-gated e-signature flow — where the
 * slice goes live. The owner opens the emailed link (no login; PropertyOwner is
 * a contact), reads the agreement, verifies an SMS OTP, and signs. Signing
 * records the tamper-evident evidence, transitions Sent -> Signed, and hands off
 * to AgreementApplicator which activates the agreement and LOCKS the governed
 * fee. The token is the security boundary; these routes are intentionally
 * unauthenticated and OTP-gated.
 */
class AgreementSigningController extends Controller
{
    public function __construct(
        private readonly SmsOtpService $otp,
        private readonly AgreementApplicator $applicator,
        private readonly DocumensoService $documenso,
        private readonly AgreementPdfRenderer $pdfRenderer,
    ) {}

    public function show(string $token): Response
    {
        $signature = $this->signatureByToken($token);

        if ($signature->status === AgreementSignatureStatus::Signed) {
            return Inertia::render('Agreements/Sign', [
                'token' => $token,
                'signed' => true,
                'agreement' => ['title' => $signature->agreement?->title],
            ]);
        }

        abort_unless($signature->isPending(), 404);
        $agreement = $signature->agreement;
        abort_if($agreement === null, 404);

        return Inertia::render('Agreements/Sign', [
            'token' => $token,
            'signed' => false,
            'signerName' => $signature->signer_name,
            'phoneHint' => $this->maskPhone($signature->signer_phone),
            'agreement' => [
                'title' => $agreement->title,
                'rendered_body' => $agreement->rendered_body,
                'content_hash' => $agreement->content_hash,
            ],
        ]);
    }

    public function requestOtp(string $token): RedirectResponse
    {
        $signature = $this->signatureByToken($token);
        abort_unless($signature->isPending(), 404);

        $this->otp->generateAndSend(
            (string) $signature->signer_phone,
            $this->otpKey($token),
            __('agreements.sign.otp_sms'),
        );

        return back()->with('success', __('agreements.sign.otp_sent'));
    }

    public function sign(SignAgreementRequest $request, string $token): RedirectResponse|Response
    {
        $signature = $this->signatureByToken($token);
        abort_unless($signature->isPending(), 404);

        $agreement = $signature->agreement;
        abort_if($agreement === null, 404);

        // Tamper check: the owner must be signing the exact snapshot they reviewed.
        if (! hash_equals((string) $agreement->content_hash, (string) $request->string('content_hash'))) {
            return back()->withErrors(['content_hash' => __('agreements.sign.errors.changed')]);
        }

        // Throws ValidationException (field: code) on a wrong/expired OTP — before any write.
        // The OTP is the IDENTITY pre-gate; it is consumed here regardless of path.
        $this->otp->verify($this->otpKey($token), (string) $request->string('code'));

        // Preferred path: a Documenso certificate-sealed signature. The owner signs in
        // the embedded widget; the DOCUMENT_COMPLETED webhook seals the evidence and
        // activates the fee (so the signature stays Pending here — NOT yet activated).
        $envelope = $this->prepareDocumensoEnvelope($signature, $agreement);
        if ($envelope !== null) {
            $signature->forceFill([
                'otp_verified_at' => now(),
                'content_hash' => $agreement->content_hash,
                'documenso_document_id' => $envelope->documentId,
                'documenso_recipient_token' => $envelope->recipientToken,
                'documenso_status' => DocumensoDocumentStatus::Pending,
            ])->save();

            return $this->embedResponse($token, $signature, $agreement, $envelope);
        }

        // Fallback: Documenso unconfigured/unreachable/no signer email -> in-house assent,
        // exactly as PR 2.3c. A Documenso outage never blocks the owner from signing.
        $this->signInHouse($signature, $agreement, $request);

        return redirect()->route('agreements.sign.show', $token)->with('success', __('agreements.sign.thanks'));
    }

    /**
     * Build (or reuse) a Documenso signing envelope for the owner. Returns null when
     * Documenso is unavailable for ANY reason (unconfigured, no signer email, or a
     * DocumensoException) so the caller falls back to the in-house assent.
     */
    private function prepareDocumensoEnvelope(AgreementSignature $signature, ManagementAgreement $agreement): ?DocumensoEnvelope
    {
        $email = (string) $signature->signer_email;
        // A present-but-malformed email would make DocumensoSigner throw — validate
        // here so it degrades to the in-house fallback instead of 500-ing the owner.
        if ((string) config('documenso.base_url') === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        // Retry after an abandoned embed: reuse the existing envelope, never create a dup.
        if ($signature->documenso_document_id !== null && (string) $signature->documenso_recipient_token !== '') {
            return new DocumensoEnvelope($signature->documenso_document_id, (string) $signature->documenso_recipient_token, '');
        }

        try {
            $signer = new DocumensoSigner((string) $signature->signer_name, $email);
            $pdf = $this->pdfRenderer->render($agreement);

            return $this->documenso->createSigningEnvelope($pdf, $signer, (string) $agreement->title, (string) $signature->id);
        } catch (\Throwable $e) {
            // ANY failure preparing the Documenso path — unavailable (DocumensoException),
            // a malformed email (InvalidArgumentException), or a PDF render error — degrades
            // to the in-house assent: the owner is NEVER blocked. Logged at error WITH the
            // exception class so a genuine bug stays distinguishable from a Documenso outage.
            Log::error('Documenso signing preparation failed; falling back to in-house sign', [
                'signature_id' => $signature->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function embedResponse(string $token, AgreementSignature $signature, ManagementAgreement $agreement, DocumensoEnvelope $envelope): Response
    {
        return Inertia::render('Agreements/Sign', [
            'token' => $token,
            'signed' => false,
            'signerName' => $signature->signer_name,
            'phoneHint' => $this->maskPhone($signature->signer_phone),
            'agreement' => [
                'title' => $agreement->title,
                'rendered_body' => $agreement->rendered_body,
                'content_hash' => $agreement->content_hash,
            ],
            // The recipient token is the signing-session credential the iframe needs; the
            // base URL is the public Documenso host. No platform secret is exposed.
            'embed' => [
                'baseUrl' => rtrim((string) config('documenso.base_url'), '/'),
                'token' => $envelope->recipientToken,
                'signerName' => (string) $signature->signer_name,
                'signerEmail' => (string) $signature->signer_email,
            ],
        ]);
    }

    private function signInHouse(AgreementSignature $signature, ManagementAgreement $agreement, SignAgreementRequest $request): void
    {
        DB::transaction(function () use ($signature, $agreement, $request): void {
            $signature->forceFill([
                'status' => AgreementSignatureStatus::Signed,
                'signing_method' => AgreementSignatureMethod::InHouse,
                'content_hash' => $agreement->content_hash,
                'otp_verified_at' => now(),
                'signed_at' => now(),
                'signed_ip' => $request->ip(),
                'signed_user_agent' => substr((string) $request->userAgent(), 0, 255),
            ])->save();

            $agreement->forceFill([
                'status' => AgreementStatus::Signed,
                'signed_at' => now(),
            ])->save();

            // Activates Signed -> Active and writes + LOCKS the governed fee.
            $this->applicator->activate($agreement);
        });
    }

    private function signatureByToken(string $token): AgreementSignature
    {
        // Public, unauthenticated route: the 64-char token is the credential, so
        // the query runs without tenant scope (no authed user to scope to anyway).
        return AgreementSignature::query()
            ->with('agreement')
            ->where('token', $token)
            ->firstOrFail();
    }

    private function otpKey(string $token): string
    {
        return "otp:agreement-sign:{$token}";
    }

    private function maskPhone(?string $phone): string
    {
        $phone = (string) $phone;

        return $phone === '' ? '' : str_repeat('•', max(0, strlen($phone) - 4)).substr($phone, -4);
    }
}
