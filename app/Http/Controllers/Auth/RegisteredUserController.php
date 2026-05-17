<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LandlordWelcome;
use App\Models\Invitation;
use App\Models\OnboardingSession;
use App\Models\User;
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
    public function create(Request $request): Response
    {
        $invitationToken = $request->query('invitation');
        $invitationRole = null;
        $invitationEmail = null;

        if ($invitationToken) {
            $invitation = Invitation::where('token', $invitationToken)
                ->whereNull('accepted_at')
                ->first();
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
