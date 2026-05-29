<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use App\Services\Sms\SmsOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

/**
 * Phase-45 EMERGENCY-CONTACT-SMS-1/2: tenant triggers an SMS OTP to
 * their emergency contact, then submits the 6-digit code to mark the
 * row verified. Rate-limited: max 3 sends per contact per 24 hours.
 */
class EmergencyContactVerificationController extends Controller
{
    public const MAX_SENDS_PER_24H = 3;

    public function __construct(private readonly SmsOtpService $otp) {}

    public function sendOtp(Request $request, EmergencyContact $contact): RedirectResponse
    {
        $this->guard($request, $contact);

        // Rate-limit window resets every 24h based on last_otp_sent_at.
        if ($contact->last_otp_sent_at !== null && $contact->last_otp_sent_at->diffInHours(now()) < 24) {
            if ($contact->verification_attempts_24h >= self::MAX_SENDS_PER_24H) {
                return Redirect::back()->withErrors([
                    'phone' => __('tenant.emergency_contact.rate_limited'),
                ]);
            }
        } else {
            // > 24h since last send — reset counter.
            $contact->verification_attempts_24h = 0;
        }

        $this->otp->generateAndSend(
            $contact->phone,
            'otp:contact:'.$contact->id,
            __('tenant.emergency_contact.otp_message'),
        );

        $contact->update([
            'last_otp_sent_at' => now(),
            'verification_attempts_24h' => $contact->verification_attempts_24h + 1,
        ]);

        return Redirect::back()->with('success', __('tenant.emergency_contact.otp_sent'));
    }

    public function verifyOtp(Request $request, EmergencyContact $contact): RedirectResponse
    {
        $this->guard($request, $contact);

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $this->otp->verify('otp:contact:'.$contact->id, $validated['code']);
        } catch (ValidationException $e) {
            return Redirect::back()->withErrors($e->errors());
        }

        $contact->update(['verified_at' => now()]);

        return Redirect::back()->with('success', __('tenant.emergency_contact.verified'));
    }

    private function guard(Request $request, EmergencyContact $contact): void
    {
        abort_unless(
            $contact->tenant_id === $request->user()->id,
            403,
            'You can only verify your own emergency contacts.',
        );
    }
}
