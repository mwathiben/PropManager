<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingResumeLink;
use App\Models\OnboardingSession;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Phase-46 PROGRESS-RESUME-1: signed onboarding-resume URLs with audit.
 *
 * generate(OnboardingSession): string builds a Laravel temporarySignedRoute
 * (7-day expiry) keyed on the session id + persists an onboarding_resume_link
 * audit row with the SHA-256 hash of the URL signature so replay detection
 * doesn't need to deserialise the URL.
 *
 * consume(OnboardingSession, string $signature, ?string $ip): void looks up
 * the audit row by signature_hash, asserts not-yet-consumed + not-expired,
 * writes consumed_at + consumed_from_ip. Throws ValidationException on
 * replay / expiry.
 */
class OnboardingResumeService
{
    public const EXPIRY_DAYS = 7;

    public function generate(OnboardingSession $session, ?int $generatedByUserId = null): string
    {
        $expiry = now()->addDays(self::EXPIRY_DAYS);

        $url = URL::temporarySignedRoute(
            'onboarding.resume',
            $expiry,
            ['session' => $session->id],
        );

        $signature = $this->extractSignature($url);

        OnboardingResumeLink::create([
            'onboarding_session_id' => $session->id,
            'signature_hash' => hash('sha256', $signature),
            'signed_until' => $expiry,
            'generated_at' => now(),
            'generated_by_user_id' => $generatedByUserId,
        ]);

        return $url;
    }

    public function consume(OnboardingSession $session, string $signature, ?string $ip = null): void
    {
        $hash = hash('sha256', $signature);

        $link = OnboardingResumeLink::query()
            ->where('onboarding_session_id', $session->id)
            ->where('signature_hash', $hash)
            ->first();

        if ($link === null) {
            throw ValidationException::withMessages(['signature' => 'Resume link not recognised.']);
        }
        if ($link->isConsumed()) {
            throw ValidationException::withMessages(['signature' => 'Resume link already consumed.']);
        }
        if ($link->signed_until->isPast()) {
            throw ValidationException::withMessages(['signature' => 'Resume link expired.']);
        }

        $link->update([
            'consumed_at' => now(),
            'consumed_from_ip' => $ip,
        ]);
    }

    private function extractSignature(string $url): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        return (string) ($query['signature'] ?? '');
    }
}
