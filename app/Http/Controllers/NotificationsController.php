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
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Repositories\Contracts\NotificationDefaultsRepositoryInterface;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use App\Services\SchedulerService;
use App\Services\TemplateService;
use App\Services\WhatsAppTemplateService;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    protected WhatsAppTemplateService $whatsAppTemplateService;

    protected NotificationConfigRepositoryInterface $configRepository;

    protected NotificationDefaultsRepositoryInterface $defaultsRepository;

    public function __construct(
        NotificationService $notificationService,
        TemplateService $templateService,
        SchedulerService $schedulerService,
        PushNotificationService $pushService,
        WhatsAppTemplateService $whatsAppTemplateService,
        NotificationConfigRepositoryInterface $configRepository,
        NotificationDefaultsRepositoryInterface $defaultsRepository
    ) {
        $this->notificationService = $notificationService;
        $this->templateService = $templateService;
        $this->schedulerService = $schedulerService;
        $this->pushService = $pushService;
        $this->whatsAppTemplateService = $whatsAppTemplateService;
        $this->configRepository = $configRepository;
        $this->defaultsRepository = $defaultsRepository;
    }

    public function index(Request $request): Response
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

    public function send(Request $request): RedirectResponse
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
        dispatch(SendNotificationJob::forNew(
            $validated['recipient_id'],
            $validated['type'],
            $validated['subject'],
            $validated['message'],
            $validated['data'] ?? null,
            $landlordId
        ));

        return redirect()->back()->with('success', 'Notification queued for sending.');
    }

    public function sendBulk(Request $request): RedirectResponse
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

    public function sendRentReminders(Request $request): RedirectResponse
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
                dispatch(SendNotificationJob::forNew(
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
                ));

                $sent++;
            }
        }

        return redirect()->back()->with('success', "Rent reminders queued for {$sent} tenants.");
    }

    public function sendArrearsNotices(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Get all active leases that have overdue invoices
        $leases = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->whereHas('invoices', function ($query) {
                $query->whereIn('status', ['overdue', 'partial', 'sent'])
                    ->whereColumn('amount_paid', '<', 'total_due');
            })
            ->with(['tenant:id,name', 'invoices' => function ($query) {
                $query->whereIn('status', ['overdue', 'partial', 'sent'])
                    ->whereColumn('amount_paid', '<', 'total_due');
            }])
            ->get();

        $sent = 0;

        foreach ($leases as $lease) {
            if ($lease->tenant) {
                $arrearsAmount = $lease->invoices->sum(fn ($inv) => $inv->total_due - $inv->amount_paid);

                if ($arrearsAmount > 0) {
                    dispatch(SendNotificationJob::forNew(
                        $lease->tenant_id,
                        'arrears_notice',
                        'Payment Overdue - Arrears Notice',
                        sprintf(
                            "Hello %s,\n\nYou have an outstanding balance of KES %s. Please clear your arrears as soon as possible.\n\nThank you.",
                            $lease->tenant->name,
                            number_format($arrearsAmount, 2)
                        ),
                        [
                            'lease_id' => $lease->id,
                            'arrears_amount' => $arrearsAmount,
                        ],
                        $landlordId
                    ));

                    $sent++;
                }
            }
        }

        return redirect()->back()->with('success', "Arrears notices queued for {$sent} tenants.");
    }

    public function getPreferences(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'tenant' ? $user->landlord_id : $user->id;

        $preferences = NotificationPreference::getOrCreate($user->id, $landlordId);

        return response()->json($preferences);
    }

    public function updatePreferences(Request $request): RedirectResponse
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
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^(\+?[1-9]\d{1,14}|0[71]\d{8})$/'],
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'tenant' ? $user->landlord_id : $user->id;

        $preferences = NotificationPreference::getOrCreate($user->id, $landlordId);
        $preferences->update($validated);

        return redirect()->back()->with('success', 'Notification preferences updated successfully.');
    }

    public function markAsRead(Notification $notification): RedirectResponse
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

    public function retry(Notification $notification): RedirectResponse
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

        dispatch(SendNotificationJob::forNew(
            $notification->recipient_id,
            $notification->type,
            $notification->subject,
            $notification->message,
            $notification->data,
            $notification->landlord_id
        ));

        return redirect()->back()->with('success', 'Notification queued for retry.');
    }

    public function destroy(Notification $notification): RedirectResponse
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

    public function storeTemplate(Request $request): RedirectResponse
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

    public function updateTemplate(NotificationTemplate $template, Request $request): RedirectResponse
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

    public function destroyTemplate(NotificationTemplate $template): RedirectResponse
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

    public function storeSchedule(Request $request): RedirectResponse
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

    public function updateSchedule(NotificationSchedule $schedule, Request $request): RedirectResponse
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

    public function toggleSchedule(NotificationSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $schedule->update(['is_active' => ! $schedule->is_active]);

        $status = $schedule->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Schedule {$status} successfully.");
    }

    public function destroySchedule(NotificationSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $schedule->delete();

        return redirect()->back()->with('success', 'Schedule deleted successfully.');
    }

    public function runScheduleNow(NotificationSchedule $schedule): RedirectResponse
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

        $smsProvider = $this->configRepository->getSmsProvider($landlordId);
        $twilioCredentials = $this->configRepository->getTwilioCredentials($landlordId);
        $atCredentials = $this->configRepository->getAfricasTalkingCredentials($landlordId);
        $whatsappNumber = $this->configRepository->getWhatsAppNumber($landlordId);

        // Get provider configurations
        $providers = [
            'email' => [
                'configured' => true, // Email is always configured via Laravel
                'provider' => 'Laravel Mail',
            ],
            'sms' => [
                'configured' => $smsProvider !== 'none',
                'provider' => $smsProvider,
                'has_credentials' => ! empty($twilioCredentials['account_sid']) || ! empty($atCredentials['api_key']),
            ],
            'whatsapp' => [
                'configured' => ! empty($whatsappNumber),
                'has_credentials' => ! empty($twilioCredentials['account_sid']),
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
            'currentSmsProvider' => $smsProvider,
            'globalPreferences' => $this->loadGlobalPreferences($landlordId),
            'setupComplete' => $this->isSetupComplete($landlordId),
            'buildings' => $this->getBuildingsForFilter(),
            'tenants' => [],
            'notifications' => ['data' => []],
            'filters' => [],
            'whatsappTemplates' => $this->whatsAppTemplateService->getTemplatesWithStatus($landlordId),
        ]);
    }

    public function updateProviderSettings(Request $request, string $provider): RedirectResponse
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

    public function updateWhatsAppTemplates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'templates' => 'required|array',
            'templates.*.type' => 'required|string|max:50',
            'templates.*.sid' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        foreach ($validated['templates'] as $template) {
            if (! empty($template['sid'])) {
                $this->configRepository->setWhatsAppTemplateSid($landlordId, $template['type'], $template['sid']);
            } else {
                // Delete template by setting empty via legacy method (to maintain backwards compatibility)
                Setting::where('landlord_id', $landlordId)
                    ->where('key', "whatsapp_template_{$template['type']}_sid")
                    ->delete();
            }
        }

        return redirect()->back()->with('success', 'WhatsApp templates updated successfully.');
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
                'sms' => $this->configRepository->isProviderConfigured($landlordId, 'sms'),
                'whatsapp' => $this->configRepository->isProviderConfigured($landlordId, 'whatsapp'),
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

    public function completeSetup(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $this->configRepository->markSetupComplete($landlordId);

        return redirect()->route('notifications.overview')->with('success', 'Notification setup completed successfully!');
    }

    /**
     * Check if setup is complete
     */
    private function isSetupComplete(int $landlordId): bool
    {
        // Check if explicitly marked as complete
        if ($this->configRepository->isSetupComplete($landlordId)) {
            return true;
        }

        // Or if at least one additional channel besides email is configured
        $smsConfigured = $this->configRepository->isProviderConfigured($landlordId, 'sms');
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

        $this->configRepository->setEmailCredentials($landlordId, [
            'mailer' => $validated['mail_mailer'] ?? null,
            'host' => $validated['mail_host'] ?? null,
            'port' => $validated['mail_port'] ?? null,
            'username' => $validated['mail_username'] ?? null,
            'password' => $validated['mail_password'] ?? null,
            'encryption' => $validated['mail_encryption'] ?? null,
            'from_address' => $validated['mail_from_address'] ?? null,
            'from_name' => $validated['mail_from_name'] ?? null,
            'enabled' => $validated['enabled'] ?? true,
        ]);
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

        $this->configRepository->setSmsProvider($landlordId, $validated['sms_provider']);

        if ($validated['sms_provider'] === 'twilio') {
            $this->configRepository->setTwilioCredentials($landlordId, [
                'account_sid' => $validated['twilio_account_sid'] ?? null,
                'auth_token' => $validated['twilio_auth_token'] ?? null,
                'phone_number' => $validated['twilio_phone_number'] ?? null,
            ]);
        } elseif ($validated['sms_provider'] === 'africas_talking') {
            $this->configRepository->setAfricasTalkingCredentials($landlordId, [
                'api_key' => $validated['africas_talking_api_key'] ?? null,
                'username' => $validated['africas_talking_username'] ?? null,
                'from' => $validated['africas_talking_from'] ?? null,
            ]);
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
            $this->configRepository->setWhatsAppNumber($landlordId, $validated['twilio_whatsapp_number']);
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
        $provider = $this->configRepository->getSmsProvider($landlordId);

        if ($provider === 'none') {
            return ['success' => false, 'message' => 'No SMS provider configured'];
        }

        // Just verify credentials exist for now
        if ($provider === 'twilio') {
            $credentials = $this->configRepository->getTwilioCredentials($landlordId);
            $hasCredentials = ! empty($credentials['account_sid']) && ! empty($credentials['auth_token']);

            return [
                'success' => $hasCredentials,
                'message' => $hasCredentials ? 'Twilio credentials configured' : 'Twilio credentials missing',
            ];
        }

        if ($provider === 'africas_talking') {
            $credentials = $this->configRepository->getAfricasTalkingCredentials($landlordId);
            $hasCredentials = ! empty($credentials['api_key']) && ! empty($credentials['username']);

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

    public function updateGlobalPreferences(Request $request): RedirectResponse
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

        $this->defaultsRepository->updateDefaults($landlordId, [
            'quiet_hours_enabled' => $validated['quiet_hours_enabled'] ?? false,
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? '22:00',
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? '08:00',
            'quiet_hours_queue_notifications' => $validated['quiet_hours_queue_notifications'] ?? true,
            'max_retries' => $validated['notification_max_retries'] ?? 3,
            'retry_delay_minutes' => $validated['notification_retry_delay'] ?? 5,
            'daily_limit_per_tenant' => $validated['notification_daily_limit_per_tenant'] ?? 20,
            'hourly_limit_per_tenant' => $validated['notification_hourly_limit_per_tenant'] ?? 5,
            'sender_name' => $validated['notification_sender_name'] ?? '',
            'reply_to_email' => $validated['notification_reply_to_email'] ?? '',
            'archive_days' => $validated['notification_archive_days'] ?? 90,
            'track_read_status' => $validated['notification_track_read_status'] ?? true,
            'reminder_days_before_due' => $validated['default_rent_reminder_days'] ?? 7,
            'default_channels' => $validated['default_notification_channels'] ?? ['email'],
        ]);

        return redirect()->back()->with('success', 'Global preferences saved successfully.');
    }

    /**
     * Load global preferences for a landlord
     */
    private function loadGlobalPreferences(int $landlordId): array
    {
        $defaults = $this->defaultsRepository->getDefaults($landlordId);

        return [
            // Quiet Hours
            'quiet_hours_enabled' => $defaults['quiet_hours_enabled'],
            'quiet_hours_start' => $defaults['quiet_hours_start'],
            'quiet_hours_end' => $defaults['quiet_hours_end'],
            'quiet_hours_queue_notifications' => $defaults['quiet_hours_queue_notifications'],

            // Retry Configuration
            'notification_max_retries' => $defaults['max_retries'],
            'notification_retry_delay' => $defaults['retry_delay_minutes'],

            // Rate Limiting
            'notification_daily_limit_per_tenant' => $defaults['daily_limit_per_tenant'],
            'notification_hourly_limit_per_tenant' => $defaults['hourly_limit_per_tenant'],

            // Sender Information
            'notification_sender_name' => $defaults['sender_name'] ?? '',
            'notification_reply_to_email' => $defaults['reply_to_email'] ?? '',

            // Archive Settings
            'notification_archive_days' => $defaults['archive_days'],
            'notification_track_read_status' => $defaults['track_read_status'],

            // Default Preferences
            'default_rent_reminder_days' => $defaults['reminder_days_before_due'],
            'default_notification_channels' => $defaults['default_channels'],
        ];
    }
}
