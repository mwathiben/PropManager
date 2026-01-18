<?php

namespace App\Http\Controllers;

use App\Events\InvitationAccepted;
use App\Mail\CaretakerInvitation;
use App\Models\Invitation;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Display a listing of invitations for the authenticated landlord
     */
    public function index()
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

    /**
     * Store a newly created invitation and send email
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'property_id' => 'required|exists:properties,id',
        ]);

        // Check if user already exists as a caretaker for this landlord
        $existingCaretaker = User::where('email', $request->email)
            ->where('role', 'caretaker')
            ->where('landlord_id', auth()->id())
            ->first();

        if ($existingCaretaker) {
            return back()->with('error', 'This email is already registered as a caretaker for your properties.');
        }

        // Check if pending invitation already exists
        $existingInvitation = Invitation::where('email', $request->email)
            ->where('landlord_id', auth()->id())
            ->where('property_id', $request->property_id)
            ->pending()
            ->first();

        if ($existingInvitation) {
            return back()->with('error', 'A pending invitation already exists for this email and property.');
        }

        // Verify landlord owns the property
        $property = Property::where('id', $request->property_id)
            ->where('landlord_id', auth()->id())
            ->firstOrFail();

        // Check if the email belongs to an existing user in the system
        $existingUser = User::where('email', $request->email)->first();

        try {
            // Create invitation with target_user_id if user exists
            $invitation = Invitation::create([
                'landlord_id' => auth()->id(),
                'email' => $request->email,
                'target_user_id' => $existingUser?->id,
                'property_id' => $request->property_id,
                'token' => Invitation::generateToken(),
            ]);

            $invitation->load('property', 'landlord');

            // Send invitation email
            Mail::to($request->email)->send(new CaretakerInvitation($invitation));

            // If existing user, also create in-app notification
            if ($existingUser) {
                $notificationService = app(NotificationService::class);
                $notificationService->sendCaretakerInvitation(
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
            return back()->with('error', 'Failed to send invitation. Please try again.');
        }
    }

    /**
     * Show the invitation acceptance page (public route)
     */
    public function show($token)
    {
        $invitation = Invitation::where('token', $token)
            ->with(['landlord', 'property'])
            ->firstOrFail();

        // Check if already accepted
        if ($invitation->isAccepted()) {
            return Inertia::render('Invitations/Accept', [
                'invitation' => null,
                'error' => 'This invitation has already been accepted.',
            ]);
        }

        // Check if expired
        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Accept', [
                'invitation' => null,
                'error' => 'This invitation has expired. Please contact the property owner for a new invitation.',
            ]);
        }

        // Check if this invitation targets an existing user
        if ($invitation->isForExistingUser()) {
            // If user is not logged in, redirect to login
            if (! auth()->check()) {
                session(['url.intended' => route('invitations.show', $token)]);

                return redirect()->route('login')
                    ->with('message', 'Please log in to accept this caretaker invitation.');
            }

            // If logged in as the target user, show simplified acceptance page
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

            // Logged in as a different user
            return Inertia::render('Invitations/Accept', [
                'invitation' => null,
                'error' => 'This invitation is for a different account. Please log in with the correct email address.',
            ]);
        }

        // New user flow - show registration form
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

    /**
     * Accept the invitation and create caretaker account
     */
    public function accept(Request $request, $token)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'mobile_number' => 'nullable|string|max:20',
        ]);

        $invitation = Invitation::where('token', $token)->firstOrFail();

        // Validate invitation
        if ($invitation->isAccepted()) {
            return back()->with('error', 'This invitation has already been accepted.');
        }

        if ($invitation->isExpired()) {
            return back()->with('error', 'This invitation has expired.');
        }

        // Check if user already exists
        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            return back()->with('error', 'An account with this email already exists. Please login instead.');
        }

        try {
            DB::beginTransaction();

            // Create caretaker user
            $user = User::create([
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'role' => 'caretaker',
                'landlord_id' => $invitation->landlord_id,
                'mobile_number' => $request->mobile_number,
            ]);

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            DB::commit();

            // Broadcast to landlord dashboard
            event(new InvitationAccepted($invitation, $user));

            // Log the user in
            auth()->login($user);

            return redirect()->route('dashboard')->with('success', 'Welcome! Your caretaker account has been created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create account. Please try again.');
        }
    }

    /**
     * Resend an invitation email
     */
    public function resend(Invitation $invitation)
    {
        // Verify ownership
        if ($invitation->landlord_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        // Can't resend accepted invitations
        if ($invitation->isAccepted()) {
            return back()->with('error', 'Cannot resend an accepted invitation.');
        }

        try {
            $invitation->load('property', 'landlord');
            Mail::to($invitation->email)->send(new CaretakerInvitation($invitation));

            return back()->with('success', 'Invitation resent successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to resend invitation.');
        }
    }

    /**
     * Cancel/delete an invitation
     */
    public function destroy(Invitation $invitation)
    {
        // Verify ownership
        if ($invitation->landlord_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        // Can't delete accepted invitations
        if ($invitation->isAccepted()) {
            return back()->with('error', 'Cannot delete an accepted invitation.');
        }

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled successfully.');
    }

    /**
     * Accept invitation for an existing authenticated user
     */
    public function acceptAuthenticated(Request $request, Invitation $invitation)
    {
        $user = auth()->user();

        // Verify this invitation is for the authenticated user
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
            DB::beginTransaction();

            // Update user to caretaker role
            $user->update([
                'role' => 'caretaker',
                'landlord_id' => $invitation->landlord_id,
            ]);

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            // Mark related in-app notification as read
            Notification::withoutGlobalScope('landlord')
                ->where('recipient_id', $user->id)
                ->where('type', Notification::TYPE_CARETAKER_INVITATION)
                ->where('channel', 'in_app')
                ->whereNull('read_at')
                ->whereJsonContains('data->invitation_id', $invitation->id)
                ->update(['read_at' => now(), 'status' => 'read']);

            DB::commit();

            // Broadcast to landlord dashboard
            event(new InvitationAccepted($invitation, $user));

            return redirect()->route('dashboard')
                ->with('success', 'Welcome! You are now a caretaker for '.$invitation->property->name.'.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['invitation' => 'Failed to accept invitation. Please try again.']);
        }
    }

    /**
     * Decline invitation for an existing authenticated user
     */
    public function declineAuthenticated(Request $request, Invitation $invitation)
    {
        $user = auth()->user();

        // Verify this invitation is for the authenticated user
        if ($invitation->target_user_id !== $user->id) {
            abort(403, 'This invitation is not for you.');
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'Cannot decline an already accepted invitation.']);
        }

        try {
            DB::beginTransaction();

            // Delete the invitation
            $invitation->delete();

            // Mark related in-app notification as read
            Notification::withoutGlobalScope('landlord')
                ->where('recipient_id', $user->id)
                ->where('type', Notification::TYPE_CARETAKER_INVITATION)
                ->where('channel', 'in_app')
                ->whereNull('read_at')
                ->whereJsonContains('data->invitation_id', $invitation->id)
                ->update(['read_at' => now(), 'status' => 'read']);

            DB::commit();

            return back()->with('success', 'Invitation declined successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['invitation' => 'Failed to decline invitation. Please try again.']);
        }
    }
}
