<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Traits\WithLandlordScope;
use App\Mail\OwnerInvitation as OwnerInvitationMail;
use App\Models\Invitation;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-102 OWNER-PORTAL: a dedicated invite flow that gives a PropertyOwner (a
 * Phase-101 contact) a login. The landlord invites the owner; the owner accepts a
 * deep-link, which mints an `owner` User, links it to the PropertyOwner, and drops
 * them into their portal. Invite-only (never self-registerable).
 */
class OwnerInvitationController extends Controller
{
    use WithLandlordScope;

    /** Landlord invites an existing owner contact to create their login. */
    public function store(PropertyOwner $owner): RedirectResponse
    {
        $landlordId = $this->getLandlordId();
        abort_unless((int) $owner->landlord_id === $landlordId, 404);
        abort_if($owner->user_id !== null, 422);

        if (blank($owner->email)) {
            return back()->withErrors(['email' => __('owners.invite.no_email')]);
        }
        if (User::where('email', $owner->email)->exists()) {
            return back()->withErrors(['email' => __('owners.invite.email_taken')]);
        }

        // One live token per owner — the invite button stays until acceptance.
        $hasPending = Invitation::where('role', 'owner')
            ->where('property_owner_id', $owner->id)
            ->whereNull('accepted_at')
            ->where('created_at', '>', now()->subDays(30))
            ->exists();
        if ($hasPending) {
            return back()->withErrors(['email' => __('owners.invite.already_pending')]);
        }

        $invitation = Invitation::create([
            'landlord_id' => $landlordId,
            'email' => $owner->email,
            'role' => 'owner',
            'property_owner_id' => $owner->id,
            'token' => Invitation::generateToken(),
        ]);

        Mail::to($owner->email)->queue(new OwnerInvitationMail($invitation));

        return back()->with('success', __('owners.invite.sent'));
    }

    /** Public: the invitee opens the deep-link. */
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)
            ->where('role', 'owner')
            ->with(['landlord:id,name', 'propertyOwner:id,name'])
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->with('error', __('owners.invite.used'));
        }
        if ($invitation->isExpired()) {
            return redirect()->route('login')->with('error', __('owners.invite.expired'));
        }
        // A null FK means the owner contact was deleted after sending (nullOnDelete).
        if ($invitation->property_owner_id === null) {
            return redirect()->route('login')->with('error', __('owners.invite.revoked'));
        }

        if ($invitation->viewed_at === null) {
            $invitation->forceFill(['viewed_at' => now()])->saveQuietly();
        }

        return Inertia::render('Owner/AcceptInvitation', [
            'invitation' => [
                'email' => $invitation->email,
                'token' => $invitation->token,
                'landlord_name' => $invitation->landlord->name,
                'owner_name' => $invitation->propertyOwner?->name,
                'expires_at' => $invitation->getExpiresAt()->format('F d, Y'),
            ],
        ]);
    }

    /** Public: the invitee sets their password and lands in the portal. */
    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->where('role', 'owner')->firstOrFail();

        if (! $invitation->isValid()) {
            return back()->with('error', __('owners.invite.used'));
        }
        if ($invitation->property_owner_id === null) {
            return back()->with('error', __('owners.invite.revoked'));
        }
        if (User::where('email', $invitation->email)->exists()) {
            return back()->with('error', __('owners.invite.email_taken'));
        }

        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated, $invitation) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($validated['password']),
                    'mobile_number' => $validated['mobile_number'] ?? null,
                    // Accepting the deep-link proves email ownership.
                    'email_verified_at' => now(),
                ]);
                // role/landlord_id are mass-assignment-guarded — set explicitly.
                $user->role = 'owner';
                $user->landlord_id = $invitation->landlord_id;
                $user->save();

                // Claim an UNCLAIMED owner contact; a 0-row update means another token
                // raced us or the contact was deleted — throw to roll the accept back.
                $linked = PropertyOwner::withoutGlobalScope('landlord')
                    ->where('id', $invitation->property_owner_id)
                    ->where('landlord_id', $invitation->landlord_id)
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->id]);
                if ($linked === 0) {
                    throw new \RuntimeException('Owner already claimed or unavailable.');
                }

                $invitation->markAsAccepted();

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to accept owner invitation', ['token' => $token, 'error' => $e->getMessage()]);

            return back()->with('error', __('owners.invite.failed'));
        }

        app(\App\Services\SecurityLogger::class)->logRoleChange($user, 'none', $user->role, $invitation->landlord);

        auth()->login($user);
        $request->session()->regenerate();

        // Owners have no multi-step onboarding — straight to their portal.
        return redirect()->route('owner-portal.dashboard')
            ->with('success', __('owners.invite.welcome'));
    }
}
