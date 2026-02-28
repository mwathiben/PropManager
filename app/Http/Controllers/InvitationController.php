<?php

namespace App\Http\Controllers;

use App\Events\InvitationAccepted;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Mail\CaretakerInvitation;
use App\Models\Invitation;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function index(): Response
    {
        $invitations = Invitation::where('landlord_id', auth()->id())
            ->with('property')
            ->latest()
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'property' => $invitation->property->name,
                    'property_id' => $invitation->property_id,
                    'token' => $invitation->token,
                    'status' => $invitation->isAccepted() ? 'accepted' : ($invitation->isExpired() ? 'expired' : 'pending'),
                    'sent_at' => $invitation->created_at->format('M d, Y'),
                    'accepted_at' => $invitation->accepted_at?->format('M d, Y'),
                    'is_valid' => $invitation->isValid(),
                ];
            });

        $properties = Property::where('landlord_id', auth()->id())
            ->select('id', 'name')
            ->get();

        return Inertia::render('Invitations/Index', [
            'invitations' => $invitations,
            'properties' => $properties,
        ]);
    }

    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        $existingCaretaker = User::where('email', $request->email)
            ->where('role', 'caretaker')
            ->where('landlord_id', auth()->id())
            ->first();

        if ($existingCaretaker) {
            return back()->with('error', 'This email is already registered as a caretaker for your properties.');
        }

        $existingInvitation = Invitation::where('email', $request->email)
            ->where('landlord_id', auth()->id())
            ->where('property_id', $request->property_id)
            ->pending()
            ->first();

        if ($existingInvitation) {
            return back()->with('error', 'A pending invitation already exists for this email and property.');
        }

        $property = Property::where('id', $request->property_id)
            ->where('landlord_id', auth()->id())
            ->firstOrFail();

        $existingUser = User::where('email', $request->email)->first();

        try {
            $invitation = DB::transaction(function () use ($request, $existingUser) {
                $invitation = Invitation::create([
                    'landlord_id' => auth()->id(),
                    'email' => $request->email,
                    'target_user_id' => $existingUser?->id,
                    'property_id' => $request->property_id,
                    'token' => Invitation::generateToken(),
                ]);

                $invitation->load('property', 'landlord');

                return $invitation;
            });

            Mail::to($request->email)->send(new CaretakerInvitation($invitation));

            if ($existingUser) {
                app(NotificationService::class)->sendCaretakerInvitation(
                    $existingUser->id,
                    [
                        'invitation_id' => $invitation->id,
                        'invitation_token' => $invitation->token,
                        'landlord_name' => auth()->user()->name,
                        'property_name' => $invitation->property->name,
                        'expires_at' => $invitation->getExpiresAt()->format('F d, Y'),
                    ],
                    auth()->id()
                );
            }

            return back()->with('success', 'Invitation sent successfully to '.$request->email);
        } catch (\Exception $e) {
            Log::error('Failed to send caretaker invitation', [
                'email' => $request->email,
                'property_id' => $request->property_id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to send invitation. Please try again.');
        }
    }

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)
            ->with(['landlord', 'property'])
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return $this->renderInvitationError('This invitation has already been accepted.');
        }

        if ($invitation->isExpired()) {
            return $this->renderInvitationError('This invitation has expired. Please contact the property owner for a new invitation.');
        }

        if ($invitation->isForExistingUser()) {
            return $this->handleExistingUserFlow($invitation, $token);
        }

        return $this->renderNewUserFlow($invitation);
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isAccepted()) {
            return back()->with('error', 'This invitation has already been accepted.');
        }

        if ($invitation->isExpired()) {
            return back()->with('error', 'This invitation has expired.');
        }

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            return back()->with('error', 'An account with this email already exists. Please login instead.');
        }

        try {
            [$user, $invitation] = DB::transaction(function () use ($request, $invitation) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $invitation->email,
                    'password' => Hash::make($request->password),
                    'role' => 'caretaker',
                    'landlord_id' => $invitation->landlord_id,
                    'mobile_number' => $request->mobile_number,
                ]);

                $invitation->markAsAccepted();

                return [$user, $invitation];
            });

            event(new InvitationAccepted($invitation, $user));

            auth()->login($user);

            return redirect()->route('dashboard')->with('success', 'Welcome! Your caretaker account has been created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to accept caretaker invitation', [
                'token' => $token,
                'email' => $invitation->email,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to create account. Please try again.');
        }
    }

    public function resend(Invitation $invitation): RedirectResponse
    {
        $this->authorize('resend', $invitation);

        try {
            $invitation->load('property', 'landlord');
            Mail::to($invitation->email)->send(new CaretakerInvitation($invitation));

            return back()->with('success', 'Invitation resent successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to resend caretaker invitation', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to resend invitation.');
        }
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        if ($invitation->isAccepted()) {
            return back()->with('error', 'Cannot delete an accepted invitation.');
        }

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled successfully.');
    }

    public function acceptAuthenticated(Request $request, Invitation $invitation): RedirectResponse
    {
        $user = auth()->user();

        if ($invitation->target_user_id !== $user->id) {
            abort(403, 'This invitation is not for you.');
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            return back()->withErrors(['invitation' => 'This invitation has expired.']);
        }

        try {
            DB::transaction(function () use ($user, $invitation) {
                $user->update([
                    'role' => 'caretaker',
                    'landlord_id' => $invitation->landlord_id,
                ]);

                $invitation->markAsAccepted();

                $this->markRelatedNotificationAsRead($user->id, $invitation->id);
            });

            event(new InvitationAccepted($invitation, $user));

            return redirect()->route('dashboard')
                ->with('success', 'Welcome! You are now a caretaker for '.$invitation->property->name.'.');
        } catch (\Exception $e) {
            Log::error('Failed to accept authenticated caretaker invitation', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['invitation' => 'Failed to accept invitation. Please try again.']);
        }
    }

    public function declineAuthenticated(Request $request, Invitation $invitation): RedirectResponse
    {
        $user = auth()->user();

        if ($invitation->target_user_id !== $user->id) {
            abort(403, 'This invitation is not for you.');
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'Cannot decline an already accepted invitation.']);
        }

        try {
            DB::transaction(function () use ($user, $invitation) {
                $invitation->delete();

                $this->markRelatedNotificationAsRead($user->id, $invitation->id);
            });

            return back()->with('success', 'Invitation declined successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to decline caretaker invitation', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['invitation' => 'Failed to decline invitation. Please try again.']);
        }
    }

    private function renderInvitationError(string $message): Response
    {
        return Inertia::render('Invitations/Accept', [
            'invitation' => null,
            'error' => $message,
        ]);
    }

    private function handleExistingUserFlow(Invitation $invitation, string $token): Response|RedirectResponse
    {
        if (! auth()->check()) {
            session(['url.intended' => route('invitations.show', $token)]);

            return redirect()->route('login')
                ->with('message', 'Please log in to accept this caretaker invitation.');
        }

        if (auth()->id() === $invitation->target_user_id) {
            return Inertia::render('Invitations/AcceptExisting', [
                'invitation' => [
                    'id' => $invitation->id,
                    'landlord_name' => $invitation->landlord->name,
                    'property_name' => $invitation->property->name,
                    'expires_at' => $invitation->getExpiresAt()->format('F d, Y'),
                ],
            ]);
        }

        return $this->renderInvitationError('This invitation is for a different account. Please log in with the correct email address.');
    }

    private function renderNewUserFlow(Invitation $invitation): Response
    {
        return Inertia::render('Invitations/Accept', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'token' => $invitation->token,
                'landlord_name' => $invitation->landlord->name,
                'property_name' => $invitation->property->name,
                'expires_at' => $invitation->getExpiresAt()->format('F d, Y'),
            ],
            'error' => null,
        ]);
    }

    private function markRelatedNotificationAsRead(int $userId, int $invitationId): void
    {
        Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $userId)
            ->where('type', Notification::TYPE_CARETAKER_INVITATION)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->whereJsonContains('data->invitation_id', $invitationId)
            ->update(['read_at' => now(), 'status' => 'read']);
    }
}
