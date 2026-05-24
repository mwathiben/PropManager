<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LandlordWelcome;
use App\Models\AttributionTouchpoint;
use App\Models\Invitation;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Services\Growth\AttributionTouchpointRecorder;
use App\Services\Growth\FunnelEventEmitter;
use App\Services\Growth\FunnelStage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Phase-46 ROLE-PATHS-1: surfacing the role list to the Register
     * view lets the form pick the right one. The Invitation model can
     * also pre-fill the role.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $invitationToken = $request->query('invitation');
        $invitationRole = null;
        $invitationEmail = null;

        if ($invitationToken) {
            $invitation = Invitation::where('token', $invitationToken)
                ->whereNull('accepted_at')
                ->first();

            // Water clients are landlord-provisioned via their own deep-link
            // (WaterClientInvitationController), which sets landlord_id. The public
            // /register path never sets it, so route the invitee to the correct flow
            // rather than rendering a register form that would 403 on submit.
            if ($invitation?->role === 'water_client') {
                return redirect()->route('water-invite.show', $invitationToken);
            }
            // Phase-102: owners are landlord-provisioned via their own deep-link too.
            if ($invitation?->role === 'owner') {
                return redirect()->route('owner-invite.show', $invitationToken);
            }

            $invitationRole = $invitation?->role;
            $invitationEmail = $invitation?->email;
        }

        return Inertia::render('Auth/Register', [
            'invitationRole' => $invitationRole,
            'invitationEmail' => $invitationEmail,
        ]);
    }

    /**
     * Phase-46 ROLE-PATHS-1: accept role from form or invitation.
     * Pre-Phase-46 hardcoded role='tenant' so landlords could never
     * self-register — they had to be promoted by a super_admin or
     * invited via an admin flow that didn't exist.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // Water clients are landlord-provisioned at onboarding (Phase 95), NOT
            // self-registered — self-registering one would fail the onboarding_sessions
            // role ENUM and dead-end with no dashboard. Keep it off the public gate.
            'role' => ['nullable', 'string', 'in:landlord,caretaker,tenant'],
            'invitation_token' => ['nullable', 'string'],
        ]);

        // Invitation override: if a valid token was supplied, the
        // invitation's role beats the form's role choice (landlord
        // intended the role; signup shouldn't allow escalation).
        $resolvedRole = $request->input('role', 'tenant');
        $invitation = null;

        if ($request->filled('invitation_token')) {
            $invitation = Invitation::where('token', $request->input('invitation_token'))
                ->whereNull('accepted_at')
                ->first();
            if ($invitation !== null) {
                // Hard gate: a water_client invitation must NOT mint a user here —
                // /register never sets landlord_id, so it would orphan the account
                // (unscoped, null currency) AND burn the one-time token, denying the
                // legitimate deep-link accept. They onboard via WaterClientInvitationController.
                abort_if(in_array($invitation->role, ['water_client', 'owner'], true), 403);
                $resolvedRole = $invitation->role;
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->role = $resolvedRole;
        $user->save();

        // Phase-46 WIZARD-INFRA-1: mint an OnboardingSession on signup.
        // The wizard reads this row; firstFor() handles all the find-or-create logic.
        OnboardingSession::firstFor($user);

        if ($invitation !== null) {
            $invitation->update(['accepted_at' => now()]);
        }

        // Phase-56 MULTI-TOUCH-1: record the registration touchpoint so
        // AttributionModelService has something to allocate credit across.
        $channel = match (true) {
            $invitation !== null => AttributionTouchpoint::CHANNEL_INVITATION,
            $request->session()->has('referral_code') => AttributionTouchpoint::CHANNEL_REFERRAL,
            default => AttributionTouchpoint::CHANNEL_DIRECT,
        };
        app(AttributionTouchpointRecorder::class)->record(
            user: $user,
            channel: $channel,
            campaign: $request->session()->get('referral_code'),
        );

        // Phase-56 COHORT-BY-SOURCE-1: stamp acquisition_source on the user
        // row so cohort analysis can partition retention curves by source.
        $user->acquisition_source = match (true) {
            $invitation !== null => 'invitation',
            $request->session()->has('referral_code') => 'referral',
            default => 'organic',
        };
        $user->save();

        // Phase-56 FUNNEL-SANKEY-1: emit the canonical funnel.signup event.
        app(FunnelEventEmitter::class)->emit($user, FunnelStage::SIGNUP, ['role' => $resolvedRole]);

        event(new Registered($user));

        // HANDLE-6: queue welcome mail so an SMTP hiccup doesn't 500 the
        // registration response.
        if ($user->role === 'landlord') {
            Mail::to($user)->queue(new LandlordWelcome($user));
        }

        Auth::login($user);

        // CRYPTO-5: rotate the session id across the privilege transition
        // so a fixated cookie can't ride the registration flow.
        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }
}
