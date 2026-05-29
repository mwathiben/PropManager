<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSession;
use App\Services\Onboarding\OnboardingResumeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

/**
 * Phase-46 PROGRESS-RESUME-1: handles signed onboarding-resume URLs.
 * The route is named 'onboarding.resume' and is gated by the 'signed'
 * middleware (Laravel's built-in signed-URL verification). On top of
 * that, OnboardingResumeService::consume marks the audit row consumed
 * so replays are refused even if the signature is still cryptographically
 * valid.
 *
 * Flow:
 *  1. Landlord clicks the resume URL from a nudge email.
 *  2. 'signed' middleware verifies the URL hasn't been tampered with.
 *  3. consume() asserts not-yet-consumed + not-expired (defense in depth).
 *  4. If the user is not logged in, redirect to login with intended URL.
 *  5. If the user owns the session, redirect to the wizard's current step.
 */
class OnboardingResumeRedirectController extends Controller
{
    public function __construct(private readonly OnboardingResumeService $service) {}

    public function __invoke(Request $request, OnboardingSession $session): RedirectResponse
    {
        $signature = (string) $request->query('signature', '');

        try {
            $this->service->consume($session, $signature, $request->ip());
        } catch (ValidationException $e) {
            return Redirect::route('login')->withErrors($e->errors());
        }

        // Auth gate: the consumer must be the session owner.
        if (Auth::check() && Auth::id() !== $session->user_id) {
            throw new AuthorizationException('You may not resume another user\'s onboarding session.');
        }
        if (! Auth::check()) {
            return Redirect::guest(route('login'));
        }

        return Redirect::route('onboarding.step', ['step' => $session->current_step]);
    }
}
