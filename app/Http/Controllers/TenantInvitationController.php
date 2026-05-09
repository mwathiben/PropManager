<?php

namespace App\Http\Controllers;

use App\Mail\TenantInvitationMail;
use App\Mail\TenantWelcome;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\TenantInvitation;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class TenantInvitationController extends Controller
{
    protected NotificationConfigRepositoryInterface $configRepository;

    public function __construct(NotificationConfigRepositoryInterface $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * Display a listing of tenant invitations for the authenticated landlord/caretaker
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $invitations = TenantInvitation::where('landlord_id', $landlordId)
            ->with(['unit.building.property', 'initiator'])
            ->latest()
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'tenant_name' => $invitation->tenant_name,
                    'tenant_phone' => $invitation->tenant_phone,
                    'unit_id' => $invitation->unit_id,
                    'unit' => $invitation->unit->unit_number,
                    'building' => $invitation->unit->building->name,
                    'building_id' => $invitation->unit->building_id,
                    'property' => $invitation->unit->building->property->name,
                    'rent_amount' => $invitation->rent_amount,
                    'service_charge' => $invitation->service_charge,
                    'deposit_amount' => $invitation->deposit_amount,
                    'start_date' => $invitation->start_date->format('Y-m-d'),
                    'start_date_display' => $invitation->start_date->format('M d, Y'),
                    'end_date' => $invitation->end_date?->format('Y-m-d'),
                    'end_date_display' => $invitation->end_date?->format('M d, Y'),
                    'status' => $invitation->isAccepted() ? 'accepted' : ($invitation->isExpired() ? 'expired' : 'pending'),
                    'sent_at' => $invitation->created_at->format('M d, Y'),
                    'accepted_at' => $invitation->accepted_at?->format('M d, Y'),
                    'expires_at' => $invitation->expires_at->format('M d, Y'),
                    'initiated_by' => $invitation->initiator->name,
                    'is_existing_user' => $invitation->isForExistingUser(),
                    'is_valid' => $invitation->isValid(),
                    'token' => $invitation->token,
                    'notification_channels' => $invitation->notification_channels ?? ['email'],
                    'viewed_at' => $invitation->viewed_at,
                ];
            });

        // Get vacant units for the invitation form
        $vacantUnits = Unit::where('status', 'vacant')
            ->whereHas('building', function ($q) use ($landlordId) {
                $q->whereHas('property', function ($q2) use ($landlordId) {
                    $q2->where('landlord_id', $landlordId);
                });
            })
            ->with('building.property')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'floor_number' => $unit->floor_number,
                    'target_rent' => $unit->target_rent,
                    'building_id' => $unit->building_id,
                    'building_name' => $unit->building->name,
                    'property_name' => $unit->building->property->name,
                ];
            });

        // Check for edit query param
        $editInvitation = null;
        if ($request->has('edit')) {
            $editInvitation = $invitations->firstWhere('id', (int) $request->get('edit'));
        }

        // Check notification configuration
        $smsConfigured = self::isSmsConfigured($landlordId);
        $whatsappConfigured = self::isWhatsAppConfigured($landlordId);

        return Inertia::render('TenantInvitations/Index', [
            'invitations' => $invitations,
            'vacantUnits' => $vacantUnits,
            'editInvitation' => $editInvitation,
            'smsConfigured' => $smsConfigured,
            'whatsappConfigured' => $whatsappConfigured,
        ]);
    }

    /**
     * Store a newly created tenant invitation and send via selected channels
     */
    public function store(Request $request)
    {
        $channels = $request->input('notification_channels', ['email']);

        // Build validation rules
        $rules = [
            'unit_id' => 'required|exists:units,id',
            'email' => 'required|email|max:255',
            'tenant_name' => 'nullable|string|max:255',
            'tenant_phone' => 'nullable|string|max:20',
            'rent_amount' => 'required|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'deposit_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notification_channels' => 'required|array|min:1',
            'notification_channels.*' => 'in:email,sms,whatsapp',
        ];

        // Phone is required if SMS or WhatsApp is selected
        if (array_intersect(['sms', 'whatsapp'], $channels)) {
            $rules['tenant_phone'] = 'required|string|min:10|max:20';
        }

        $request->validate($rules);

        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        // Verify user has access to this unit (via TenantScope on the unit's building)
        $unit = Unit::with('building.property')->findOrFail($request->unit_id);

        // Check if unit is already occupied
        if ($unit->status === 'occupied') {
            return back()->withErrors(['unit_id' => 'This unit is already occupied.']);
        }

        // Check if pending invitation already exists for this unit
        $existingInvitation = TenantInvitation::where('unit_id', $request->unit_id)
            ->pending()
            ->first();

        if ($existingInvitation) {
            return back()->withErrors(['unit_id' => 'A pending invitation already exists for this unit. Cancel it first to send a new one.']);
        }

        // Check if user already exists in the system
        $existingUser = User::where('email', $request->email)->first();

        try {
            DB::beginTransaction();

            // Create the invitation
            $invitation = TenantInvitation::create([
                'landlord_id' => $landlordId,
                'initiated_by' => $user->id,
                'unit_id' => $request->unit_id,
                'email' => $request->email,
                'existing_user_id' => $existingUser?->id,
                'token' => TenantInvitation::generateToken(),
                'rent_amount' => $request->rent_amount,
                'service_charge' => $request->service_charge ?? 0,
                'deposit_amount' => $request->deposit_amount,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'tenant_name' => $request->tenant_name,
                'tenant_phone' => $request->tenant_phone,
                'notification_channels' => $channels,
                'expires_at' => now()->addDays(7),
            ]);

            $invitation->load('unit.building.property', 'landlord');

            // Send invitation via all selected channels
            $this->sendInvitationNotifications($invitation);

            DB::commit();

            $channelList = implode(', ', $channels);
            $message = $existingUser
                ? "Lease invitation sent to existing user {$request->email} via {$channelList}"
                : "Invitation sent to {$request->email} via {$channelList}";

            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send tenant invitation', ['error' => $e->getMessage()]);

            return back()->withErrors(['email' => 'Failed to send invitation. Please try again.']);
        }
    }

    /**
     * Show the invitation acceptance page (PUBLIC route - no auth required)
     */
    public function show(string $token)
    {
        $invitation = TenantInvitation::where('token', $token)
            ->with(['unit.building.property', 'landlord'])
            ->first();

        if (! $invitation) {
            return Inertia::render('TenantInvitations/Accept', [
                'invitation' => null,
                'error' => 'Invalid invitation link.',
            ]);
        }

        // Check if already accepted
        if ($invitation->isAccepted()) {
            return Inertia::render('TenantInvitations/Accept', [
                'invitation' => null,
                'error' => 'This invitation has already been accepted.',
            ]);
        }

        // Check if expired
        if ($invitation->isExpired()) {
            return Inertia::render('TenantInvitations/Accept', [
                'invitation' => null,
                'error' => 'This invitation has expired. Please contact the property owner for a new invitation.',
            ]);
        }

        return Inertia::render('TenantInvitations/Accept', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'token' => $invitation->token,
                'tenant_name' => $invitation->tenant_name,
                'landlord_name' => $invitation->landlord->name,
                'property_name' => $invitation->unit->building->property->name,
                'building_name' => $invitation->unit->building->name,
                'unit_number' => $invitation->unit->unit_number,
                'floor_number' => $invitation->unit->floor_number,
                'rent_amount' => $invitation->rent_amount,
                'service_charge' => $invitation->service_charge,
                'deposit_amount' => $invitation->deposit_amount,
                'total_move_in' => $invitation->total_move_in_cost,
                'start_date' => $invitation->start_date->format('F d, Y'),
                'end_date' => $invitation->end_date?->format('F d, Y'),
                'expires_at' => $invitation->expires_at->format('F d, Y'),
                'is_existing_user' => $invitation->isForExistingUser(),
            ],
            'error' => null,
        ]);
    }

    /**
     * Accept the invitation and create tenant account + lease (PUBLIC route)
     */
    public function accept(Request $request, string $token)
    {
        $invitation = TenantInvitation::where('token', $token)->first();

        if (! $invitation) {
            return back()->withErrors(['token' => 'Invalid invitation.']);
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['token' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            return back()->withErrors(['token' => 'This invitation has expired.']);
        }

        // Different validation based on whether user exists
        if ($invitation->isForExistingUser()) {
            // Existing user just needs to confirm
            $request->validate([
                'confirm' => 'required|accepted',
            ]);
            $tenant = $invitation->existingUser;
        } else {
            // New user needs to create account
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'phone' => 'nullable|string|max:20',
                'id_number' => 'nullable|string|max:50',
            ]);
        }

        try {
            DB::beginTransaction();

            if (! $invitation->isForExistingUser()) {
                // Create new tenant user
                $tenant = User::create([
                    'name' => $request->name,
                    'email' => $invitation->email,
                    'password' => Hash::make($request->password),
                    'mobile_number' => $request->phone ?? $invitation->tenant_phone,
                    'national_id' => $request->id_number ?? $invitation->tenant_id_number,
                    'email_verified_at' => now(),
                ]);
                $tenant->role = 'tenant';
                $tenant->landlord_id = $invitation->landlord_id;
                $tenant->save();
            }

            // Create the lease
            $lease = Lease::create([
                'unit_id' => $invitation->unit_id,
                'tenant_id' => $tenant->id,
                'landlord_id' => $invitation->landlord_id,
                'start_date' => $invitation->start_date,
                'end_date' => $invitation->end_date,
                'rent_amount' => $invitation->rent_amount,
                'service_charge' => $invitation->service_charge,
                'deposit_amount' => $invitation->deposit_amount,
                'wallet_balance' => 0,
                'is_active' => true,
            ]);

            // Update unit status to occupied
            $invitation->unit->update(['status' => 'occupied']);

            // Create payment verification if landlord requires it
            $landlord = User::find($invitation->landlord_id);
            if ($landlord && $landlord->require_payment_before_access) {
                TenantPaymentVerification::create([
                    'lease_id' => $lease->id,
                    'landlord_id' => $invitation->landlord_id,
                    'status' => 'pending_payment',
                    'deposit_required' => $invitation->deposit_amount ?? 0,
                    'first_rent_required' => $invitation->rent_amount ?? 0,
                    'total_required' => ($invitation->deposit_amount ?? 0) + ($invitation->rent_amount ?? 0),
                ]);
            }

            // Auto-generate first invoice if enabled
            $invoiceSettings = $landlord?->invoiceSetting;
            if ($invoiceSettings?->auto_generate_first_invoice) {
                $invoiceService = app(InvoiceService::class);
                $invoiceService->generateFirstInvoiceForLease($lease);
            }

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            // HANDLE-6: queue welcome mail so an SMTP hiccup doesn't 500
            // the invitation-acceptance flow.
            Mail::to($tenant)->queue(new TenantWelcome($tenant, $invitation, $lease));

            DB::commit();

            // Log the user in
            auth()->login($tenant);

            return redirect()->route('dashboard')->with('success', 'Welcome! Your lease has been activated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['token' => 'Failed to process invitation. Please try again. '.$e->getMessage()]);
        }
    }

    /**
     * Resend a tenant invitation via selected channels
     */
    public function resend(Request $request, TenantInvitation $invitation)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        // Verify ownership
        if ($invitation->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }

        // Can't resend accepted invitations
        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'Cannot resend an accepted invitation.']);
        }

        try {
            // Extend expiration
            $invitation->update(['expires_at' => now()->addDays(7)]);

            $invitation->load('unit.building.property', 'landlord');

            // Resend via all originally selected channels
            $this->sendInvitationNotifications($invitation);

            $channelList = implode(', ', $invitation->notification_channels ?? ['email']);

            return back()->with('success', "Invitation resent via {$channelList}.");
        } catch (\Exception $e) {
            Log::error('Failed to resend tenant invitation', ['error' => $e->getMessage()]);

            return back()->withErrors(['invitation' => 'Failed to resend invitation.']);
        }
    }

    /**
     * Update a pending invitation (all lease terms editable until accepted)
     */
    public function update(Request $request, TenantInvitation $invitation)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        // Verify ownership
        if ($invitation->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }

        // Can't edit accepted invitations
        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'Cannot edit an accepted invitation.']);
        }

        $channels = $request->input('notification_channels', $invitation->notification_channels ?? ['email']);

        $rules = [
            'email' => 'required|email|max:255',
            'tenant_name' => 'nullable|string|max:255',
            'tenant_phone' => 'nullable|string|max:20',
            'rent_amount' => 'required|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'deposit_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notification_channels' => 'nullable|array',
            'notification_channels.*' => 'in:email,sms,whatsapp',
        ];

        // Phone required if SMS or WhatsApp selected
        if (array_intersect(['sms', 'whatsapp'], $channels)) {
            $rules['tenant_phone'] = 'required|string|min:10|max:20';
        }

        $validated = $request->validate($rules);

        // Check if email changed and update existing_user_id if needed
        $existingUser = null;
        if ($validated['email'] !== $invitation->email) {
            $existingUser = User::where('email', $validated['email'])->first();
        }

        $invitation->update([
            'email' => $validated['email'],
            'tenant_name' => $validated['tenant_name'],
            'tenant_phone' => $validated['tenant_phone'],
            'rent_amount' => $validated['rent_amount'],
            'service_charge' => $validated['service_charge'] ?? 0,
            'deposit_amount' => $validated['deposit_amount'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'notification_channels' => $channels,
            'existing_user_id' => $existingUser?->id ?? $invitation->existing_user_id,
        ]);

        return back()->with('success', 'Invitation updated successfully.');
    }

    /**
     * Cancel/delete a tenant invitation
     */
    public function destroy(TenantInvitation $invitation)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        // Verify ownership
        if ($invitation->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }

        // Can't delete accepted invitations
        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'Cannot delete an accepted invitation.']);
        }

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled successfully.');
    }

    // ==================== Notification Sending Methods ====================

    /**
     * Send invitation notifications via all selected channels
     */
    protected function sendInvitationNotifications(TenantInvitation $invitation): void
    {
        $unit = $invitation->unit;
        $propertyName = $unit->building->property->name;
        $acceptUrl = $invitation->accept_url;

        // Email
        if ($invitation->shouldSendEmail()) {
            try {
                Mail::to($invitation->email)->queue(new TenantInvitationMail($invitation));
                $invitation->update(['email_sent_at' => now()]);
            } catch (\Exception $e) {
                Log::error('Failed to send tenant invitation email', [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // SMS
        if ($invitation->shouldSendSms()) {
            $message = "You're invited to lease Unit {$unit->unit_number} at {$propertyName}. "
                     .'Rent: KES '.number_format($invitation->rent_amount).'/month. '
                     ."Accept: {$acceptUrl}";

            if ($this->sendSms($invitation->tenant_phone, $message, $invitation->landlord_id)) {
                $invitation->update(['sms_sent_at' => now()]);
            }
        }

        // WhatsApp
        if ($invitation->shouldSendWhatsApp()) {
            $message = "🏠 *Lease Invitation*\n\n"
                     ."You're invited to lease:\n"
                     ."📍 Unit {$unit->unit_number} at {$propertyName}\n"
                     .'💰 Rent: KES '.number_format($invitation->rent_amount)."/month\n"
                     .'📅 Start: '.$invitation->start_date->format('M j, Y')."\n\n"
                     ."Accept your invitation:\n{$acceptUrl}";

            if ($this->sendWhatsApp($invitation->tenant_phone, $message, $invitation->landlord_id)) {
                $invitation->update(['whatsapp_sent_at' => now()]);
            }
        }

        // In-App notification for existing users
        if ($invitation->isForExistingUser()) {
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->sendTenantInvitation(
                    $invitation->existing_user_id,
                    [
                        'invitation_id' => $invitation->id,
                        'invitation_token' => $invitation->token,
                        'landlord_name' => $invitation->landlord->name,
                        'property_name' => $propertyName,
                        'building_name' => $unit->building->name,
                        'unit_number' => $unit->unit_number,
                        'rent_amount' => $invitation->rent_amount,
                        'deposit_amount' => $invitation->deposit_amount,
                        'expires_at' => $invitation->expires_at->format('F d, Y'),
                    ],
                    $invitation->landlord_id
                );
            } catch (\Exception $e) {
                Log::error('Failed to send tenant invitation in-app notification', [
                    'invitation_id' => $invitation->id,
                    'user_id' => $invitation->existing_user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send SMS via configured provider (Twilio or Africa's Talking)
     */
    protected function sendSms(string $phone, string $message, int $landlordId): bool
    {
        $provider = $this->configRepository->getSmsProvider($landlordId);

        if ($provider === 'none') {
            Log::warning('SMS provider not configured', ['landlord_id' => $landlordId]);

            return false;
        }

        return match ($provider) {
            'twilio' => $this->sendViaTwilio($phone, $message, $landlordId),
            'africas_talking' => $this->sendViaAfricasTalking($phone, $message, $landlordId),
            default => false,
        };
    }

    /**
     * Send SMS via Twilio
     */
    protected function sendViaTwilio(string $phone, string $message, int $landlordId): bool
    {
        $credentials = $this->configRepository->getTwilioCredentials($landlordId);
        $accountSid = $credentials['account_sid'];
        $authToken = $credentials['auth_token'];
        $fromNumber = $credentials['phone_number'];

        if (! $accountSid || ! $authToken || ! $fromNumber) {
            Log::warning('Twilio credentials not configured', ['landlord_id' => $landlordId]);

            return false;
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $phone,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info('SMS sent via Twilio', ['phone' => $phone, 'sid' => $response->json('sid')]);

                return true;
            }

            Log::error('Twilio SMS failed', ['phone' => $phone, 'error' => $response->json('message')]);

            return false;
        } catch (\Exception $e) {
            Log::error('Twilio SMS exception', ['phone' => $phone, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Send SMS via Africa's Talking
     */
    protected function sendViaAfricasTalking(string $phone, string $message, int $landlordId): bool
    {
        $credentials = $this->configRepository->getAfricasTalkingCredentials($landlordId);
        $apiKey = $credentials['api_key'];
        $username = $credentials['username'];
        $from = $credentials['from'];

        if (! $apiKey || ! $username) {
            Log::warning("Africa's Talking credentials not configured", ['landlord_id' => $landlordId]);

            return false;
        }

        try {
            $response = Http::withHeaders([
                'apiKey' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.africastalking.com/version1/messaging', [
                'username' => $username,
                'to' => $phone,
                'message' => $message,
                'from' => $from,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['SMSMessageData']['Recipients'][0]['status'])
                    && $data['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
                    Log::info("SMS sent via Africa's Talking", ['phone' => $phone]);

                    return true;
                }
            }

            Log::error("Africa's Talking SMS failed", ['phone' => $phone, 'response' => $response->body()]);

            return false;
        } catch (\Exception $e) {
            Log::error("Africa's Talking SMS exception", ['phone' => $phone, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Send WhatsApp message via Twilio
     */
    protected function sendWhatsApp(string $phone, string $message, int $landlordId): bool
    {
        $credentials = $this->configRepository->getTwilioCredentials($landlordId);
        $accountSid = $credentials['account_sid'];
        $authToken = $credentials['auth_token'];
        $fromNumber = $this->configRepository->getWhatsAppNumber($landlordId);

        if (! $accountSid || ! $authToken || ! $fromNumber) {
            Log::warning('WhatsApp (Twilio) credentials not configured', ['landlord_id' => $landlordId]);

            return false;
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => 'whatsapp:'.$fromNumber,
                    'To' => 'whatsapp:'.$phone,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent via Twilio', ['phone' => $phone, 'sid' => $response->json('sid')]);

                return true;
            }

            Log::error('Twilio WhatsApp failed', ['phone' => $phone, 'error' => $response->json('message')]);

            return false;
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp exception', ['phone' => $phone, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Check if SMS is configured for a landlord
     */
    public static function isSmsConfigured(int $landlordId): bool
    {
        $repository = app(NotificationConfigRepositoryInterface::class);

        return $repository->isProviderConfigured($landlordId, 'sms');
    }

    /**
     * Check if WhatsApp is configured for a landlord
     */
    public static function isWhatsAppConfigured(int $landlordId): bool
    {
        $repository = app(NotificationConfigRepositoryInterface::class);

        return $repository->isProviderConfigured($landlordId, 'whatsapp');
    }

    // ==================== Authenticated Accept/Decline (In-App) ====================

    /**
     * Accept invitation for authenticated existing users (in-app acceptance)
     */
    public function acceptAuthenticated(Request $request, TenantInvitation $invitation)
    {
        $user = auth()->user();

        // Verify this invitation is for the authenticated user
        if ($invitation->existing_user_id !== $user->id) {
            abort(403, 'This invitation is not for you.');
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            return back()->withErrors(['invitation' => 'This invitation has expired.']);
        }

        // Load unit without TenantScope (unit belongs to landlord, not current user)
        $unit = Unit::withoutGlobalScope('landlord')->find($invitation->unit_id);

        // Check if unit is still available
        if ($unit->status === 'occupied') {
            return back()->withErrors(['invitation' => 'This unit is no longer available.']);
        }

        try {
            DB::beginTransaction();

            // Update user to tenant role
            $user->update([
                'role' => 'tenant',
                'landlord_id' => $invitation->landlord_id,
            ]);

            // Create the lease
            $lease = Lease::create([
                'unit_id' => $invitation->unit_id,
                'tenant_id' => $user->id,
                'landlord_id' => $invitation->landlord_id,
                'start_date' => $invitation->start_date,
                'end_date' => $invitation->end_date,
                'rent_amount' => $invitation->rent_amount,
                'service_charge' => $invitation->service_charge,
                'deposit_amount' => $invitation->deposit_amount,
                'wallet_balance' => 0,
                'is_active' => true,
            ]);

            // Update unit status to occupied
            $unit->update(['status' => 'occupied']);

            // Create payment verification if landlord requires it
            $landlord = User::find($invitation->landlord_id);
            if ($landlord && $landlord->require_payment_before_access) {
                TenantPaymentVerification::create([
                    'lease_id' => $lease->id,
                    'landlord_id' => $invitation->landlord_id,
                    'status' => 'pending_payment',
                    'deposit_required' => $invitation->deposit_amount ?? 0,
                    'first_rent_required' => $invitation->rent_amount ?? 0,
                    'total_required' => ($invitation->deposit_amount ?? 0) + ($invitation->rent_amount ?? 0),
                ]);
            }

            // Auto-generate first invoice if enabled
            $invoiceSettings = $landlord?->invoiceSetting;
            if ($invoiceSettings?->auto_generate_first_invoice) {
                $invoiceService = app(InvoiceService::class);
                $invoiceService->generateFirstInvoiceForLease($lease);
            }

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            // Mark related in-app notification as read
            Notification::withoutGlobalScope('landlord')
                ->where('recipient_id', $user->id)
                ->where('type', Notification::TYPE_TENANT_INVITATION)
                ->where('channel', 'in_app')
                ->whereNull('read_at')
                ->whereJsonContains('data->invitation_id', $invitation->id)
                ->update(['read_at' => now(), 'status' => 'read']);

            // HANDLE-6: queue welcome mail; SMTP delays must not hold up the
            // existing-user invitation-acceptance flow.
            Mail::to($user)->queue(new TenantWelcome($user, $invitation, $lease));

            DB::commit();

            return redirect()->route('dashboard')->with('success', 'Congratulations! Your lease has been activated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept invitation', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['invitation' => 'Failed to accept invitation. Please try again.']);
        }
    }

    /**
     * Decline invitation for authenticated existing users (in-app decline)
     */
    public function declineAuthenticated(Request $request, TenantInvitation $invitation)
    {
        $user = auth()->user();

        // Verify this invitation is for the authenticated user
        if ($invitation->existing_user_id !== $user->id) {
            abort(403, 'This invitation is not for you.');
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['invitation' => 'This invitation has already been accepted.']);
        }

        if (! $invitation->isValid()) {
            return back()->withErrors(['invitation' => 'This invitation is no longer valid.']);
        }

        $invitation->markAsDeclined();

        // Mark related in-app notification as read
        Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->where('type', Notification::TYPE_TENANT_INVITATION)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->whereJsonContains('data->invitation_id', $invitation->id)
            ->update(['read_at' => now(), 'status' => 'read']);

        return back()->with('success', 'Invitation declined successfully.');
    }
}
