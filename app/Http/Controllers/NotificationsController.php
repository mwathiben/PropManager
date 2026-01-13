<?php

namespace App\Http\Controllers;

use App\Jobs\SendBulkNotificationsJob;
use App\Jobs\SendNotificationJob;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use App\Services\SchedulerService;
use App\Services\TemplateService;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    use HasBuildingFilter;

    protected NotificationService $notificationService;

    protected TemplateService $templateService;

    protected SchedulerService $schedulerService;

    protected PushNotificationService $pushService;

    public function __construct(
        NotificationService $notificationService,
        TemplateService $templateService,
        SchedulerService $schedulerService,
        PushNotificationService $pushService
    ) {
        $this->notificationService = $notificationService;
        $this->templateService = $templateService;
        $this->schedulerService = $schedulerService;
        $this->pushService = $pushService;
    }

    /**
     * Display notification history
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Building/Wing filter
        $buildingId = $request->filled('building_id') ? (int) $request->building_id : null;
        $wingId = $request->filled('wing_id') ? (int) $request->wing_id : null;

        $query = Notification::where('landlord_id', $landlordId)
            ->with('recipient:id,name,email')
            ->orderBy('created_at', 'desc');

        // Building/Wing filter via recipient's active lease
        if ($buildingId || $wingId) {
            $buildingIds = $this->getBuildingIds($buildingId, $wingId);
            $query->whereHas('recipient', function ($recipientQuery) use ($buildingIds) {
                $recipientQuery->whereHas('leases', function ($leaseQuery) use ($buildingIds) {
                    $leaseQuery->where('is_active', true)
                        ->whereHas('unit', function ($unitQuery) use ($buildingIds) {
                            $unitQuery->whereIn('building_id', $buildingIds);
                        });
                });
            });
        }

        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('recipient_id')) {
            $query->where('recipient_id', $request->recipient_id);
        }

        $notifications = $query->paginate(20);

        // Get tenants - optionally filtered by building
        $tenantsQuery = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->orderBy('name');

        // Filter tenants by building when a building is selected
        if ($buildingId || $wingId) {
            $buildingIds = $this->getBuildingIds($buildingId, $wingId);
            $tenantsQuery->whereHas('leases', function ($leaseQuery) use ($buildingIds) {
                $leaseQuery->where('is_active', true)
                    ->whereHas('unit', function ($unitQuery) use ($buildingIds) {
                        $unitQuery->whereIn('building_id', $buildingIds);
                    });
            });
        }

        $tenants = $tenantsQuery->get(['id', 'name', 'email']);

        // Get buildings for filter
        $buildings = $this->getBuildingsForFilter();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'tenants' => $tenants,
            'buildings' => $buildings,
            'filters' => $request->only(['type', 'channel', 'status', 'recipient_id', 'building_id', 'wing_id']),
        ]);
    }

    /**
     * Send a notification to a single recipient
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'type' => 'required|in:rent_reminder,arrears_notice,invoice,receipt,rent_hike,lease_expiry,lease_renewal,maintenance_notice,general,eviction_notice',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'send_immediately' => 'boolean',
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        if ($validated['send_immediately'] ?? false) {
            // Send immediately
            $results = $this->notificationService->send(
                $validated['recipient_id'],
                $validated['type'],
                $validated['subject'],
                $validated['message'],
                $validated['data'] ?? null,
                $landlordId
            );

            return redirect()->back()->with('success', 'Notification sent successfully.');
        }

        // Queue for background processing
        SendNotificationJob::dispatch(
            $validated['recipient_id'],
            $validated['type'],
            $validated['subject'],
            $validated['message'],
            $validated['data'] ?? null,
            $landlordId
        );

        return redirect()->back()->with('success', 'Notification queued for sending.');
    }

    /**
     * Send bulk notifications
     */
    public function sendBulk(Request $request)
    {
        $validated = $request->validate([
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'exists:users,id',
            'type' => 'required|in:rent_reminder,arrears_notice,invoice,receipt,rent_hike,lease_expiry,lease_renewal,maintenance_notice,general,eviction_notice',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'channels' => 'nullable|array',
            'channels.*' => 'in:email,sms,whatsapp',
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $channels = $validated['channels'] ?? ['email', 'sms', 'whatsapp'];

        // Queue bulk notifications
        SendBulkNotificationsJob::dispatch(
            $validated['recipient_ids'],
            $validated['type'],
            $validated['subject'],
            $validated['message'],
            $validated['data'] ?? null,
            $landlordId,
            $channels
        );

        return redirect()->back()->with('success', sprintf(
            'Bulk notification queued for %d recipients.',
            count($validated['recipient_ids'])
        ));
    }

    /**
     * Send rent reminders to all tenants with upcoming rent
     */
    public function sendRentReminders(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Get all active leases
        $leases = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->with('tenant:id,name')
            ->get();

        $sent = 0;

        foreach ($leases as $lease) {
            if ($lease->tenant) {
                SendNotificationJob::dispatch(
                    $lease->tenant_id,
                    'rent_reminder',
                    'Rent Reminder',
                    sprintf(
                        "Hello %s,\n\nYour rent of KES %s is due soon.\n\nThank you.",
                        $lease->tenant->name,
                        number_format($lease->rent_amount, 2)
                    ),
                    [
                        'lease_id' => $lease->id,
                        'amount' => $lease->rent_amount,
                        'due_date' => now()->format('Y-m-d'),
                    ],
                    $landlordId
                );

                $sent++;
            }
        }

        return redirect()->back()->with('success', "Rent reminders queued for {$sent} tenants.");
    }

    /**
     * Send arrears notices to tenants with outstanding balances
     */
    public function sendArrearsNotices(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Get all active leases with arrears
        $leases = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->where('arrears', '>', 0)
            ->with('tenant:id,name')
            ->get();

        $sent = 0;

        foreach ($leases as $lease) {
            if ($lease->tenant) {
                SendNotificationJob::dispatch(
                    $lease->tenant_id,
                    'arrears_notice',
                    'Payment Overdue - Arrears Notice',
                    sprintf(
                        "Hello %s,\n\nYou have an outstanding balance of KES %s. Please clear your arrears as soon as possible.\n\nThank you.",
                        $lease->tenant->name,
                        number_format($lease->arrears, 2)
                    ),
                    [
                        'lease_id' => $lease->id,
                        'arrears_amount' => $lease->arrears,
                    ],
                    $landlordId
                );

                $sent++;
            }
        }

        return redirect()->back()->with('success', "Arrears notices queued for {$sent} tenants.");
    }

    /**
     * Get notification preferences for current user
     */
    public function getPreferences()
    {
        $user = auth()->user();
        $landlordId = $user->role === 'tenant' ? $user->landlord_id : $user->id;

        $preferences = NotificationPreference::getOrCreate($user->id, $landlordId);

        return response()->json($preferences);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'rent_reminder_enabled' => 'boolean',
            'arrears_notice_enabled' => 'boolean',
            'invoice_enabled' => 'boolean',
            'receipt_enabled' => 'boolean',
            'rent_hike_enabled' => 'boolean',
            'lease_expiry_enabled' => 'boolean',
            'lease_renewal_enabled' => 'boolean',
            'maintenance_notice_enabled' => 'boolean',
            'general_enabled' => 'boolean',
            'eviction_notice_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'rent_reminder_days_before' => 'nullable|integer|min:1|max:30',
            'preferred_time' => 'nullable|date_format:H:i',
            'whatsapp_number' => 'nullable|string|max:20',
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'tenant' ? $user->landlord_id : $user->id;

        $preferences = NotificationPreference::getOrCreate($user->id, $landlordId);
        $preferences->update($validated);

        return redirect()->back()->with('success', 'Notification preferences updated successfully.');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification)
    {
        // Authorization check
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        if ($notification->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }

        $notification->markAsRead();

        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    /**
     * Retry failed notification
     */
    public function retry(Notification $notification)
    {
        // Authorization check
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        if ($notification->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }

        if (! $notification->isFailed()) {
            return redirect()->back()->with('error', 'Only failed notifications can be retried.');
        }

        SendNotificationJob::dispatch(
            $notification->recipient_id,
            $notification->type,
            $notification->subject,
            $notification->message,
            $notification->data,
            $notification->landlord_id
        );

        return redirect()->back()->with('success', 'Notification queued for retry.');
    }

    /**
     * Delete notification
     */
    public function destroy(Notification $notification)
    {
        // Authorization check
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        if ($notification->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }

        $notification->delete();

        return redirect()->back()->with('success', 'Notification deleted.');
    }

    // ==========================================
    // TEMPLATE METHODS
    // ==========================================

    /**
     * Display templates list
     */
    public function templates(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $templates = NotificationTemplate::where('landlord_id', $landlordId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $notificationTypes = $this->getNotificationTypes();
        $placeholders = [];
        foreach ($notificationTypes as $type) {
            $placeholders[$type['value']] = NotificationTemplate::getAllPlaceholders($type['value']);
        }

        return Inertia::render('Notifications/Index', [
            'activeTab' => 'templates',
            'templates' => $templates,
            'notificationTypes' => $notificationTypes,
            'placeholders' => $placeholders,
            'buildings' => $this->getBuildingsForFilter(),
            'tenants' => [],
            'notifications' => ['data' => []],
            'filters' => [],
        ]);
    }

    /**
     * Store a new template
     */
    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:rent_reminder,arrears_notice,invoice,receipt,rent_hike,lease_expiry,lease_renewal,maintenance_notice,general,eviction_notice',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        NotificationTemplate::create([
            'landlord_id' => $landlordId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders($validated['type'])),
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => false,
        ]);

        return redirect()->back()->with('success', 'Template created successfully.');
    }

    /**
     * Update a template
     */
    public function updateTemplate(NotificationTemplate $template, Request $request)
    {
        $this->authorizeTemplate($template);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return redirect()->back()->with('success', 'Template updated successfully.');
    }

    /**
     * Delete a template
     */
    public function destroyTemplate(NotificationTemplate $template)
    {
        $this->authorizeTemplate($template);

        if ($template->is_default) {
            return redirect()->back()->with('error', 'Cannot delete default templates.');
        }

        $template->delete();

        return redirect()->back()->with('success', 'Template deleted successfully.');
    }

    /**
     * Preview a template with sample data
     */
    public function previewTemplate(NotificationTemplate $template, Request $request): JsonResponse
    {
        $this->authorizeTemplate($template);

        $sampleContext = [
            'tenant_name' => 'John Doe',
            'tenant_email' => 'john@example.com',
            'unit_number' => 'A101',
            'building_name' => 'Sunset Apartments',
            'landlord_name' => auth()->user()->name,
            'property_name' => 'Sunset Heights',
            'current_date' => now()->format('F j, Y'),
            'rent_amount' => '25,000.00',
            'due_date' => now()->addDays(7)->format('F j, Y'),
            'days_until_due' => '7',
            'arrears_amount' => '50,000.00',
            'days_overdue' => '14',
            'invoice_number' => 'INV-2024-001',
            'total_amount' => '27,500.00',
        ];

        $rendered = $template->render($sampleContext);

        return response()->json([
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
        ]);
    }

    // ==========================================
    // SCHEDULE METHODS
    // ==========================================

    /**
     * Display schedules list
     */
    public function schedules(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $schedules = NotificationSchedule::where('landlord_id', $landlordId)
            ->with('template:id,name')
            ->orderBy('type')
            ->get()
            ->map(function ($schedule) {
                $schedule->trigger_description = $schedule->trigger_description;
                $schedule->next_run = $schedule->next_run;

                return $schedule;
            });

        $templates = NotificationTemplate::where('landlord_id', $landlordId)
            ->active()
            ->get(['id', 'name', 'type']);

        $scheduleTypes = [
            ['value' => 'rent_reminder', 'label' => 'Rent Reminder'],
            ['value' => 'arrears_notice', 'label' => 'Arrears Notice'],
            ['value' => 'lease_expiry', 'label' => 'Lease Expiry'],
        ];

        return Inertia::render('Notifications/Index', [
            'activeTab' => 'scheduled',
            'schedules' => $schedules,
            'templates' => $templates,
            'scheduleTypes' => $scheduleTypes,
            'buildings' => $this->getBuildingsForFilter(),
            'tenants' => [],
            'notifications' => ['data' => []],
            'filters' => [],
        ]);
    }

    /**
     * Store a new schedule
     */
    public function storeSchedule(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:rent_reminder,arrears_notice,lease_expiry',
            'trigger' => 'required|in:days_before_due,days_after_overdue,days_before_expiry',
            'days_offset' => 'required|integer|min:1|max:90',
            'send_time' => 'required|date_format:H:i',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp,push',
            'template_id' => 'nullable|exists:notification_templates,id',
            'is_active' => 'boolean',
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        NotificationSchedule::create([
            'landlord_id' => $landlordId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'trigger' => $validated['trigger'],
            'days_offset' => $validated['days_offset'],
            'send_time' => $validated['send_time'],
            'channels' => $validated['channels'],
            'template_id' => $validated['template_id'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Schedule created successfully.');
    }

    /**
     * Update a schedule
     */
    public function updateSchedule(NotificationSchedule $schedule, Request $request)
    {
        $this->authorizeSchedule($schedule);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'days_offset' => 'required|integer|min:1|max:90',
            'send_time' => 'required|date_format:H:i',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp,push',
            'template_id' => 'nullable|exists:notification_templates,id',
            'is_active' => 'boolean',
        ]);

        $schedule->update($validated);

        return redirect()->back()->with('success', 'Schedule updated successfully.');
    }

    /**
     * Toggle schedule active status
     */
    public function toggleSchedule(NotificationSchedule $schedule)
    {
        $this->authorizeSchedule($schedule);

        $schedule->update(['is_active' => ! $schedule->is_active]);

        $status = $schedule->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Schedule {$status} successfully.");
    }

    /**
     * Delete a schedule
     */
    public function destroySchedule(NotificationSchedule $schedule)
    {
        $this->authorizeSchedule($schedule);

        $schedule->delete();

        return redirect()->back()->with('success', 'Schedule deleted successfully.');
    }

    /**
     * Run a schedule immediately
     */
    public function runScheduleNow(NotificationSchedule $schedule)
    {
        $this->authorizeSchedule($schedule);

        $count = $this->schedulerService->runNow($schedule);

        return redirect()->back()->with('success', "Schedule executed. {$count} notifications queued.");
    }

    // ==========================================
    // SETTINGS METHODS
    // ==========================================

    /**
     * Display settings page
     */
    public function settings(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Get provider configurations
        $providers = [
            'email' => [
                'configured' => true, // Email is always configured via Laravel
                'provider' => 'Laravel Mail',
            ],
            'sms' => [
                'configured' => ! empty(Setting::get('sms_provider', null, $landlordId)) && Setting::get('sms_provider', null, $landlordId) !== 'none',
                'provider' => Setting::get('sms_provider', 'none', $landlordId),
                'has_credentials' => ! empty(Setting::get('twilio_account_sid', null, $landlordId)) || ! empty(Setting::get('africas_talking_api_key', null, $landlordId)),
            ],
            'whatsapp' => [
                'configured' => ! empty(Setting::get('twilio_whatsapp_number', null, $landlordId)),
                'has_credentials' => ! empty(Setting::get('twilio_account_sid', null, $landlordId)),
            ],
            'push' => [
                'configured' => $this->pushService->isConfigured($landlordId),
                'public_key' => $this->pushService->getPublicKey($landlordId),
            ],
        ];

        $smsProviders = [
            ['value' => 'none', 'label' => 'None (Disabled)'],
            ['value' => 'twilio', 'label' => 'Twilio'],
            ['value' => 'africas_talking', 'label' => "Africa's Talking"],
        ];

        return Inertia::render('Notifications/Index', [
            'activeTab' => 'settings',
            'providers' => $providers,
            'smsProviders' => $smsProviders,
            'currentSmsProvider' => Setting::get('sms_provider', 'none', $landlordId),
            'globalPreferences' => $this->loadGlobalPreferences($landlordId),
            'setupComplete' => $this->isSetupComplete($landlordId),
            'buildings' => $this->getBuildingsForFilter(),
            'tenants' => [],
            'notifications' => ['data' => []],
            'filters' => [],
        ]);
    }

    /**
     * Update provider settings
     */
    public function updateProviderSettings(Request $request, string $provider)
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        switch ($provider) {
            case 'email':
                $this->updateEmailSettings($request, $landlordId);
                break;
            case 'sms':
                $this->updateSmsSettings($request, $landlordId);
                break;
            case 'whatsapp':
                $this->updateWhatsAppSettings($request, $landlordId);
                break;
            case 'push':
                $this->updatePushSettings($request, $landlordId);
                break;
        }

        return redirect()->back()->with('success', 'Provider settings updated successfully.');
    }

    /**
     * Test provider connection
     */
    public function testProvider(Request $request, string $provider): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        try {
            $result = match ($provider) {
                'sms' => $this->testSmsProvider($landlordId),
                'push' => ['success' => $this->pushService->isConfigured($landlordId), 'message' => 'Push notifications configured'],
                default => ['success' => false, 'message' => 'Unknown provider'],
            };

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check setup status
     */
    public function checkSetupStatus(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        return response()->json([
            'complete' => $this->isSetupComplete($landlordId),
            'providers' => [
                'email' => true,
                'sms' => Setting::get('sms_provider', 'none', $landlordId) !== 'none',
                'whatsapp' => ! empty(Setting::get('twilio_whatsapp_number', null, $landlordId)),
                'push' => $this->pushService->isConfigured($landlordId),
            ],
        ]);
    }

    /**
     * Generate VAPID keys for push notifications
     */
    public function generateVapidKeys(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $keys = $this->pushService->generateVapidKeys();
        $this->pushService->saveVapidKeys($landlordId, $keys);

        return response()->json([
            'success' => true,
            'public_key' => $keys['public'],
        ]);
    }

    // ==========================================
    // PUSH SUBSCRIPTION METHODS
    // ==========================================

    /**
     * Subscribe to push notifications
     */
    public function subscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = auth()->user();

        $subscription = $this->pushService->subscribe($user->id, $validated);

        return response()->json([
            'success' => true,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $result = $this->pushService->unsubscribe($validated['endpoint']);

        return response()->json([
            'success' => $result,
        ]);
    }

    /**
     * Get VAPID public key
     */
    public function getVapidPublicKey(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $publicKey = $this->pushService->getPublicKey($landlordId);

        return response()->json([
            'public_key' => $publicKey,
        ]);
    }

    // ==========================================
    // OVERVIEW METHOD
    // ==========================================

    /**
     * Display overview/dashboard
     */
    public function overview(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Get statistics
        $stats = [
            'total_sent' => Notification::where('landlord_id', $landlordId)
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->count(),
            'pending' => Notification::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->count(),
            'failed' => Notification::where('landlord_id', $landlordId)
                ->where('status', 'failed')
                ->count(),
            'this_month' => Notification::where('landlord_id', $landlordId)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        // Get recent notifications
        $recentNotifications = Notification::where('landlord_id', $landlordId)
            ->with('recipient:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Channel distribution
        $channelStats = Notification::where('landlord_id', $landlordId)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        // Get tenants for quick actions
        $tenants = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Notifications/Index', [
            'activeTab' => 'overview',
            'stats' => $stats,
            'recentNotifications' => $recentNotifications,
            'channelStats' => $channelStats,
            'tenants' => $tenants,
            'buildings' => $this->getBuildingsForFilter(),
            'notifications' => ['data' => []],
            'filters' => [],
            'setupComplete' => $this->isSetupComplete($landlordId),
        ]);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if a user is authorized to access a template
     */
    private function authorizeTemplate(NotificationTemplate $template): void
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        if ($template->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Check if a user is authorized to access a schedule
     */
    private function authorizeSchedule(NotificationSchedule $schedule): void
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        if ($schedule->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Get notification types
     */
    private function getNotificationTypes(): array
    {
        return [
            ['value' => 'rent_reminder', 'label' => 'Rent Reminder'],
            ['value' => 'arrears_notice', 'label' => 'Arrears Notice'],
            ['value' => 'invoice', 'label' => 'Invoice'],
            ['value' => 'receipt', 'label' => 'Receipt'],
            ['value' => 'rent_hike', 'label' => 'Rent Hike'],
            ['value' => 'lease_expiry', 'label' => 'Lease Expiry'],
            ['value' => 'lease_renewal', 'label' => 'Lease Renewal'],
            ['value' => 'maintenance_notice', 'label' => 'Maintenance Notice'],
            ['value' => 'general', 'label' => 'General'],
            ['value' => 'eviction_notice', 'label' => 'Eviction Notice'],
        ];
    }

    /**
     * Mark setup as complete
     */
    public function completeSetup(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        Setting::set('notifications_setup_complete', true, false, 'notifications', 'Setup Completed', $landlordId);

        return redirect()->route('notifications.overview')->with('success', 'Notification setup completed successfully!');
    }

    /**
     * Check if setup is complete
     */
    private function isSetupComplete(int $landlordId): bool
    {
        // Check if explicitly marked as complete
        if (Setting::get('notifications_setup_complete', false, $landlordId)) {
            return true;
        }

        // Or if at least one additional channel besides email is configured
        $smsConfigured = Setting::get('sms_provider', 'none', $landlordId) !== 'none';
        $pushConfigured = $this->pushService->isConfigured($landlordId);

        return $smsConfigured || $pushConfigured;
    }

    /**
     * Update Email settings
     */
    private function updateEmailSettings(Request $request, int $landlordId): void
    {
        $validated = $request->validate([
            'mail_mailer' => 'nullable|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|string',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string',
            'enabled' => 'boolean',
        ]);

        // Email settings are typically stored in .env, but we can store overrides
        foreach (['mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_from_address', 'mail_from_name', 'mail_encryption'] as $key) {
            if (! empty($validated[$key])) {
                Setting::set($key, $validated[$key], false, 'email', ucwords(str_replace('_', ' ', $key)), $landlordId);
            }
        }

        if (! empty($validated['mail_password'])) {
            Setting::set('mail_password', $validated['mail_password'], true, 'email', 'Mail Password', $landlordId);
        }

        Setting::set('email_enabled', $validated['enabled'] ?? true, false, 'email', 'Email Enabled', $landlordId);
    }

    /**
     * Update SMS settings
     */
    private function updateSmsSettings(Request $request, int $landlordId): void
    {
        $validated = $request->validate([
            'sms_provider' => 'required|in:none,twilio,africas_talking',
            'twilio_account_sid' => 'nullable|string',
            'twilio_auth_token' => 'nullable|string',
            'twilio_phone_number' => 'nullable|string',
            'africas_talking_api_key' => 'nullable|string',
            'africas_talking_username' => 'nullable|string',
            'africas_talking_from' => 'nullable|string',
        ]);

        Setting::set('sms_provider', $validated['sms_provider'], false, 'sms', 'SMS Provider', $landlordId);

        if ($validated['sms_provider'] === 'twilio') {
            if (! empty($validated['twilio_account_sid'])) {
                Setting::set('twilio_account_sid', $validated['twilio_account_sid'], true, 'sms', 'Twilio Account SID', $landlordId);
            }
            if (! empty($validated['twilio_auth_token'])) {
                Setting::set('twilio_auth_token', $validated['twilio_auth_token'], true, 'sms', 'Twilio Auth Token', $landlordId);
            }
            if (! empty($validated['twilio_phone_number'])) {
                Setting::set('twilio_phone_number', $validated['twilio_phone_number'], false, 'sms', 'Twilio Phone Number', $landlordId);
            }
        } elseif ($validated['sms_provider'] === 'africas_talking') {
            if (! empty($validated['africas_talking_api_key'])) {
                Setting::set('africas_talking_api_key', $validated['africas_talking_api_key'], true, 'sms', "Africa's Talking API Key", $landlordId);
            }
            if (! empty($validated['africas_talking_username'])) {
                Setting::set('africas_talking_username', $validated['africas_talking_username'], false, 'sms', "Africa's Talking Username", $landlordId);
            }
            if (! empty($validated['africas_talking_from'])) {
                Setting::set('africas_talking_from', $validated['africas_talking_from'], false, 'sms', "Africa's Talking Sender ID", $landlordId);
            }
        }
    }

    /**
     * Update WhatsApp settings
     */
    private function updateWhatsAppSettings(Request $request, int $landlordId): void
    {
        $validated = $request->validate([
            'twilio_whatsapp_number' => 'nullable|string',
        ]);

        if (! empty($validated['twilio_whatsapp_number'])) {
            Setting::set('twilio_whatsapp_number', $validated['twilio_whatsapp_number'], false, 'whatsapp', 'Twilio WhatsApp Number', $landlordId);
        }
    }

    /**
     * Update push settings
     */
    private function updatePushSettings(Request $request, int $landlordId): void
    {
        $action = $request->input('action');

        if ($action === 'generate_keys') {
            $keys = $this->pushService->generateVapidKeys();
            $this->pushService->saveVapidKeys($landlordId, $keys);
        }
    }

    /**
     * Test SMS provider connection
     */
    private function testSmsProvider(int $landlordId): array
    {
        $provider = Setting::get('sms_provider', 'none', $landlordId);

        if ($provider === 'none') {
            return ['success' => false, 'message' => 'No SMS provider configured'];
        }

        // Just verify credentials exist for now
        if ($provider === 'twilio') {
            $hasCredentials = ! empty(Setting::get('twilio_account_sid', null, $landlordId))
                && ! empty(Setting::get('twilio_auth_token', null, $landlordId));

            return [
                'success' => $hasCredentials,
                'message' => $hasCredentials ? 'Twilio credentials configured' : 'Twilio credentials missing',
            ];
        }

        if ($provider === 'africas_talking') {
            $hasCredentials = ! empty(Setting::get('africas_talking_api_key', null, $landlordId))
                && ! empty(Setting::get('africas_talking_username', null, $landlordId));

            return [
                'success' => $hasCredentials,
                'message' => $hasCredentials ? "Africa's Talking credentials configured" : "Africa's Talking credentials missing",
            ];
        }

        return ['success' => false, 'message' => 'Unknown provider'];
    }

    /**
     * Get global notification preferences
     */
    public function getGlobalPreferences(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        return response()->json([
            'preferences' => $this->loadGlobalPreferences($landlordId),
        ]);
    }

    /**
     * Update global notification preferences
     */
    public function updateGlobalPreferences(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $validated = $request->validate([
            // Quiet Hours
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
            'quiet_hours_queue_notifications' => 'boolean',

            // Retry Configuration
            'notification_max_retries' => 'integer|min:0|max:10',
            'notification_retry_delay' => 'integer|min:1|max:60',

            // Rate Limiting
            'notification_daily_limit_per_tenant' => 'integer|min:1|max:100',
            'notification_hourly_limit_per_tenant' => 'integer|min:1|max:20',

            // Sender Information
            'notification_sender_name' => 'nullable|string|max:100',
            'notification_reply_to_email' => 'nullable|email|max:255',

            // Archive Settings
            'notification_archive_days' => 'integer|min:7|max:365',
            'notification_track_read_status' => 'boolean',

            // Default Preferences
            'default_rent_reminder_days' => 'integer|min:1|max:30',
            'default_notification_channels' => 'array',
            'default_notification_channels.*' => 'in:email,sms,whatsapp,push',
        ]);

        // Store each preference
        $category = 'notifications_global';

        // Quiet Hours
        Setting::set('quiet_hours_enabled', $validated['quiet_hours_enabled'] ?? false, false, $category, 'Quiet Hours Enabled', $landlordId);
        Setting::set('quiet_hours_start', $validated['quiet_hours_start'] ?? '22:00', false, $category, 'Quiet Hours Start', $landlordId);
        Setting::set('quiet_hours_end', $validated['quiet_hours_end'] ?? '08:00', false, $category, 'Quiet Hours End', $landlordId);
        Setting::set('quiet_hours_queue_notifications', $validated['quiet_hours_queue_notifications'] ?? true, false, $category, 'Queue During Quiet Hours', $landlordId);

        // Retry Configuration
        Setting::set('notification_max_retries', $validated['notification_max_retries'] ?? 3, false, $category, 'Max Retries', $landlordId);
        Setting::set('notification_retry_delay', $validated['notification_retry_delay'] ?? 5, false, $category, 'Retry Delay (minutes)', $landlordId);

        // Rate Limiting
        Setting::set('notification_daily_limit_per_tenant', $validated['notification_daily_limit_per_tenant'] ?? 20, false, $category, 'Daily Limit Per Tenant', $landlordId);
        Setting::set('notification_hourly_limit_per_tenant', $validated['notification_hourly_limit_per_tenant'] ?? 5, false, $category, 'Hourly Limit Per Tenant', $landlordId);

        // Sender Information
        Setting::set('notification_sender_name', $validated['notification_sender_name'] ?? '', false, $category, 'Sender Name', $landlordId);
        Setting::set('notification_reply_to_email', $validated['notification_reply_to_email'] ?? '', false, $category, 'Reply-To Email', $landlordId);

        // Archive Settings
        Setting::set('notification_archive_days', $validated['notification_archive_days'] ?? 90, false, $category, 'Archive Days', $landlordId);
        Setting::set('notification_track_read_status', $validated['notification_track_read_status'] ?? true, false, $category, 'Track Read Status', $landlordId);

        // Default Preferences
        Setting::set('default_rent_reminder_days', $validated['default_rent_reminder_days'] ?? 7, false, $category, 'Default Rent Reminder Days', $landlordId);
        Setting::set('default_notification_channels', json_encode($validated['default_notification_channels'] ?? ['email']), false, $category, 'Default Channels', $landlordId);

        return redirect()->back()->with('success', 'Global preferences saved successfully.');
    }

    /**
     * Load global preferences for a landlord
     */
    private function loadGlobalPreferences(int $landlordId): array
    {
        $defaultChannels = Setting::get('default_notification_channels', '["email"]', $landlordId);

        return [
            // Quiet Hours
            'quiet_hours_enabled' => (bool) Setting::get('quiet_hours_enabled', false, $landlordId),
            'quiet_hours_start' => Setting::get('quiet_hours_start', '22:00', $landlordId),
            'quiet_hours_end' => Setting::get('quiet_hours_end', '08:00', $landlordId),
            'quiet_hours_queue_notifications' => (bool) Setting::get('quiet_hours_queue_notifications', true, $landlordId),

            // Retry Configuration
            'notification_max_retries' => (int) Setting::get('notification_max_retries', 3, $landlordId),
            'notification_retry_delay' => (int) Setting::get('notification_retry_delay', 5, $landlordId),

            // Rate Limiting
            'notification_daily_limit_per_tenant' => (int) Setting::get('notification_daily_limit_per_tenant', 20, $landlordId),
            'notification_hourly_limit_per_tenant' => (int) Setting::get('notification_hourly_limit_per_tenant', 5, $landlordId),

            // Sender Information
            'notification_sender_name' => Setting::get('notification_sender_name', '', $landlordId),
            'notification_reply_to_email' => Setting::get('notification_reply_to_email', '', $landlordId),

            // Archive Settings
            'notification_archive_days' => (int) Setting::get('notification_archive_days', 90, $landlordId),
            'notification_track_read_status' => (bool) Setting::get('notification_track_read_status', true, $landlordId),

            // Default Preferences
            'default_rent_reminder_days' => (int) Setting::get('default_rent_reminder_days', 7, $landlordId),
            'default_notification_channels' => is_string($defaultChannels) ? json_decode($defaultChannels, true) : $defaultChannels,
        ];
    }
}
