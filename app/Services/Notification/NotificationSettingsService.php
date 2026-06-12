<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Repositories\Contracts\NotificationDefaultsRepositoryInterface;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

/**
 * Per-landlord notification provider configuration, extracted from
 * NotificationsController (M2 decomposition). Owns validating + persisting
 * each provider's settings (email / SMS / WhatsApp / push), the
 * setup-complete check, and the SMS-provider credential test. Behaviour is
 * locked by NotificationSettingsControllerTest — a verbatim move from the
 * controller's private helpers.
 */
class NotificationSettingsService
{
    public function __construct(
        private readonly NotificationConfigRepositoryInterface $configRepository,
        private readonly PushNotificationService $pushService,
        private readonly NotificationDefaultsRepositoryInterface $defaultsRepository,
    ) {}

    /**
     * Load the landlord's global notification defaults, keyed for the
     * settings UI.
     *
     * @return array<string, mixed>
     */
    public function loadGlobalPreferences(int $landlordId): array
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

    /**
     * Persist the landlord's global notification defaults from a validated
     * UpdateGlobalPreferences payload.
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateGlobalDefaults(array $validated, int $landlordId): void
    {
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
    }

    public function updateProvider(Request $request, string $provider, int $landlordId): void
    {
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
    }

    /**
     * Whether the landlord has finished notification setup: explicitly
     * marked complete, or at least one channel beyond email configured.
     */
    public function isSetupComplete(int $landlordId): bool
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
     * Test SMS provider connection.
     *
     * @return array{success: bool, message: string}
     */
    public function testSmsProvider(int $landlordId): array
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

    private function updateWhatsAppSettings(Request $request, int $landlordId): void
    {
        $validated = $request->validate([
            'twilio_whatsapp_number' => 'nullable|string',
        ]);

        if (! empty($validated['twilio_whatsapp_number'])) {
            $this->configRepository->setWhatsAppNumber($landlordId, $validated['twilio_whatsapp_number']);
        }
    }

    private function updatePushSettings(Request $request, int $landlordId): void
    {
        $action = $request->input('action');

        if ($action === 'generate_keys') {
            $keys = $this->pushService->generateVapidKeys();
            $this->pushService->saveVapidKeys($landlordId, $keys);
        }
    }
}
