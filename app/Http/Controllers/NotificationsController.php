<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\SendBulkNotificationRequest;
use App\Http\Requests\Notification\SendNotificationRequest;
use App\Http\Requests\Notification\SubscribePushRequest;
use App\Http\Requests\Notification\UnsubscribePushRequest;
use App\Http\Requests\Notification\UpdateGlobalPreferencesRequest;
use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Notification\UpdateWhatsAppTemplatesRequest;
use App\Jobs\SendBulkNotificationsJob;
use App\Jobs\SendNotificationJob;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Repositories\Contracts\NotificationDefaultsRepositoryInterface;
use App\Services\Notification\BulkReminderDispatcher;
use App\Services\Notification\NotificationSettingsService;
use App\Services\Notification\ProviderStatusCollector;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use App\Services\WhatsAppTemplateService;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    use HasBuildingFilter;

    protected NotificationService $notificationService;

    protected PushNotificationService $pushService;

    protected WhatsAppTemplateService $whatsAppTemplateService;

    protected NotificationConfigRepositoryInterface $configRepository;

    protected NotificationDefaultsRepositoryInterface $defaultsRepository;

    protected NotificationSettingsService $settingsService;

    protected BulkReminderDispatcher $bulkReminderDispatcher;

    public function __construct(
        NotificationService $notificationService,
        PushNotificationService $pushService,
        WhatsAppTemplateService $whatsAppTemplateService,
        NotificationConfigRepositoryInterface $configRepository,
        NotificationDefaultsRepositoryInterface $defaultsRepository,
        NotificationSettingsService $settingsService,
        BulkReminderDispatcher $bulkReminderDispatcher
    ) {
        $this->notificationService = $notificationService;
        $this->pushService = $pushService;
        $this->whatsAppTemplateService = $whatsAppTemplateService;
        $this->configRepository = $configRepository;
        $this->defaultsRepository = $defaultsRepository;
        $this->settingsService = $settingsService;
        $this->bulkReminderDispatcher = $bulkReminderDispatcher;
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

    public function send(SendNotificationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

    public function sendBulk(SendBulkNotificationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

        $sent = $this->bulkReminderDispatcher->dispatchRentReminders($landlordId);

        return redirect()->back()->with('success', "Rent reminders queued for {$sent} tenants.");
    }

    public function sendArrearsNotices(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $sent = $this->bulkReminderDispatcher->dispatchArrearsNotices($landlordId);

        return redirect()->back()->with('success', "Arrears notices queued for {$sent} tenants.");
    }

    public function emailPreferences(Request $request): RedirectResponse
    {
        $user = User::findOrFail($request->query('user'));

        if ($user->role !== 'tenant') {
            Log::channel('security')->warning('Email preferences: non-tenant user ID in signed URL', [
                'user_id' => $user->id,
                'role' => $user->role,
                'ip' => $request->ip(),
            ]);
            abort(403, 'Invalid email preferences link.');
        }

        // PRIV-3: log the auto-login on the SecurityLog so every signed-URL
        // identity switch is auditable, not just AdminController impersonation.
        app(\App\Services\SecurityLogger::class)->log(
            'signed_link_login',
            "Tenant {$user->email} auto-logged-in via email-preferences signed link",
            [
                'user_id' => $user->id,
                'route' => 'email.preferences',
            ],
            \App\Models\SecurityLog::SEVERITY_INFO,
            $user,
        );

        Auth::login($user);

        // CRYPTO-5: rotate the session id across the privilege transition.
        $request->session()->regenerate();

        return redirect()->route('profile.edit', ['tab' => 'notifications']);
    }

    public function oneClickUnsubscribe(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->query('user'));

        if ($user->role !== 'tenant') {
            Log::channel('security')->warning('One-click unsubscribe: non-tenant user ID in signed URL', [
                'user_id' => $user->id,
                'role' => $user->role,
                'ip' => $request->ip(),
            ]);
            abort(403, 'Invalid unsubscribe link.');
        }

        NotificationPreference::getOrCreate($user->id, $user->landlord_id)
            ->update(['email_enabled' => false]);

        Log::channel('security')->info('One-click email unsubscribe', [
            'action' => 'email_unsubscribe',
            'user_id' => $user->id,
            'landlord_id' => $user->landlord_id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['status' => 'unsubscribed']);
    }

    public function getPreferences(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'tenant' ? $user->landlord_id : $user->id;

        $preferences = NotificationPreference::getOrCreate($user->id, $landlordId);

        return response()->json($preferences);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
    // SETTINGS METHODS
    // ==========================================

    /**
     * Display settings page
     */
    public function settings(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $providerCollector = app(ProviderStatusCollector::class);

        return Inertia::render('Notifications/Index', [
            'activeTab' => 'settings',
            'providers' => $providerCollector->collect($landlordId),
            'smsProviders' => ProviderStatusCollector::getSmsProviderOptions(),
            'currentSmsProvider' => $providerCollector->getCurrentSmsProvider($landlordId),
            'globalPreferences' => $this->loadGlobalPreferences($landlordId),
            'setupComplete' => $this->settingsService->isSetupComplete($landlordId),
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

        $this->settingsService->updateProvider($request, $provider, $landlordId);

        return redirect()->back()->with('success', 'Provider settings updated successfully.');
    }

    public function updateWhatsAppTemplates(UpdateWhatsAppTemplatesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
                'sms' => $this->settingsService->testSmsProvider($landlordId),
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
            'complete' => $this->settingsService->isSetupComplete($landlordId),
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
    public function subscribePush(SubscribePushRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function unsubscribePush(UnsubscribePushRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
            'setupComplete' => $this->settingsService->isSetupComplete($landlordId),
        ]);
    }

    public function completeSetup(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $this->configRepository->markSetupComplete($landlordId);

        return redirect()->route('notifications.overview')->with('success', 'Notification setup completed successfully!');
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

    public function updateGlobalPreferences(UpdateGlobalPreferencesRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $validated = $request->validated();

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
