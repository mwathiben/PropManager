<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Http\Requests\SignAgreementRequest;
use App\Models\AgreementSignature;
use App\Services\Agreements\AgreementApplicator;
use App\Services\Sms\SmsOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

    public function requestOtp(string $token, SmsOtpService $otp): RedirectResponse
    {
        $signature = $this->signatureByToken($token);
        abort_unless($signature->isPending(), 404);

        $otp->generateAndSend(
            (string) $signature->signer_phone,
            $this->otpKey($token),
            __('agreements.sign.otp_sms'),
        );

        return back()->with('success', __('agreements.sign.otp_sent'));
    }

    public function sign(SignAgreementRequest $request, string $token, SmsOtpService $otp, AgreementApplicator $applicator): RedirectResponse
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
        $otp->verify($this->otpKey($token), (string) $request->string('code'));

        DB::transaction(function () use ($signature, $agreement, $request, $applicator): void {
            $signature->forceFill([
                'status' => AgreementSignatureStatus::Signed,
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
            $applicator->activate($agreement);
        });

        return redirect()->route('agreements.sign.show', $token)->with('success', __('agreements.sign.thanks'));
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
