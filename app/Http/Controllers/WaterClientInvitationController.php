<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\Water\StoreWaterClientInvitationRequest;
use App\Http\Traits\WithLandlordScope;
use App\Mail\WaterClientInvitation as WaterClientInvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\WaterConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-95 WATER-CLIENT-ONBOARDING: a dedicated invite flow for non-tenant water
 * clients (kept separate from the caretaker InvitationController). The landlord
 * invites a connection's client; the client accepts a deep-link, which mints a
 * water_client User, links the connection, and drops them into onboarding.
 */
class WaterClientInvitationController extends Controller
{
    use WithLandlordScope;

    /** Landlord invites the client for a specific water connection. */
    public function store(StoreWaterClientInvitationRequest $request, WaterConnection $waterConnection): RedirectResponse
    {
        abort_unless($waterConnection->landlord_id === $this->getLandlordId(), 403);

        $email = $request->validated()['email'];

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => __('water.clients.invite_email_taken')]);
        }

        // Don't mint a second live token while one is still outstanding — the invite
        // button stays visible until acceptance (user_id is only set on accept), so a
        // double-click would otherwise create two valid deep-links for one connection.
        $hasPending = Invitation::where('role', 'water_client')
            ->where('email', $email)
            ->where('water_connection_id', $waterConnection->id)
            ->whereNull('accepted_at')
            ->where('created_at', '>', now()->subDays(30))
            ->exists();
        if ($hasPending) {
            return back()->withErrors(['email' => __('water.clients.invite_already_pending')]);
        }

        $invitation = Invitation::create([
            'landlord_id' => $this->getLandlordId(),
            'email' => $email,
            'role' => 'water_client',
            'water_connection_id' => $waterConnection->id,
            'token' => Invitation::generateToken(),
        ]);

        Mail::to($email)->queue(new WaterClientInvitationMail($invitation));

        return back()->with('success', __('water.clients.invite_sent'));
    }

    /** Public: the invitee opens the deep-link. */
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)
            ->where('role', 'water_client')
            ->with(['landlord:id,name', 'waterConnection:id,identifier'])
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->with('error', __('water.clients.invite_used'));
        }
        if ($invitation->isExpired()) {
            return redirect()->route('login')->with('error', __('water.clients.invite_expired'));
        }
        // Every water-client invite provisions a connection; a null FK means the line
        // was deleted after sending (nullOnDelete). Refuse rather than onboard a client
        // onto a water line that no longer exists.
        if ($invitation->water_connection_id === null) {
            return redirect()->route('login')->with('error', __('water.clients.invite_revoked'));
        }

        if ($invitation->viewed_at === null) {
            $invitation->forceFill(['viewed_at' => now()])->saveQuietly();
        }

        return Inertia::render('WaterClient/AcceptInvitation', [
            'invitation' => [
                'email' => $invitation->email,
                'token' => $invitation->token,
                'landlord_name' => $invitation->landlord->name,
                'identifier' => $invitation->waterConnection?->identifier,
                'expires_at' => $invitation->getExpiresAt()->format('F d, Y'),
            ],
        ]);
    }

    /** Public: the invitee creates their account. */
    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->where('role', 'water_client')->firstOrFail();

        if (! $invitation->isValid()) {
            return back()->with('error', __('water.clients.invite_used'));
        }
        if ($invitation->water_connection_id === null) {
            return back()->with('error', __('water.clients.invite_revoked'));
        }
        if (User::where('email', $invitation->email)->exists()) {
            return back()->with('error', __('water.clients.invite_email_taken'));
        }

        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated, $invitation) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($validated['password']),
                    'mobile_number' => $validated['mobile_number'] ?? null,
                    // Accepting the deep-link proves email ownership — verify so the
                    // onboarding routes (verified middleware) don't bounce them.
                    'email_verified_at' => now(),
                ]);
                // role/landlord_id are guarded against mass assignment — set explicitly.
                $user->role = 'water_client';
                $user->landlord_id = $invitation->landlord_id;
                $user->save();

                // Link only an UNCLAIMED, live connection; a 0-row update means the
                // line was already claimed (a duplicate token raced us) or deleted —
                // throw to roll the whole accept back rather than mint an orphan.
                $linked = WaterConnection::withoutGlobalScope('landlord')
                    ->where('id', $invitation->water_connection_id)
                    ->where('landlord_id', $invitation->landlord_id)
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->id]);
                if ($linked === 0) {
                    throw new \RuntimeException('Water connection already claimed or unavailable.');
                }

                $invitation->markAsAccepted();

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to accept water-client invitation', ['token' => $token, 'error' => $e->getMessage()]);

            return back()->with('error', __('water.clients.invite_failed'));
        }

        app(\App\Services\SecurityLogger::class)->logRoleChange($user, 'none', $user->role, $invitation->landlord);

        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding.step', ['step' => 1])
            ->with('success', __('water.clients.invite_welcome'));
    }
}
