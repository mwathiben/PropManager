<?php

namespace App\Console\Commands;

use App\Models\NotificationDefaults;
use App\Models\NotificationPreference;
use App\Models\NotificationProviderConfig;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillNotificationConfiguration extends Command
{
    protected $signature = 'notification:backfill
                            {--dry-run : Simulate migration without writing data}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Backfill notification configuration from Settings to new tables';

    private bool $dryRun = false;

    private array $stats = [
        'sms_migrated' => 0,
        'whatsapp_migrated' => 0,
        'defaults_created' => 0,
        'preferences_updated' => 0,
    ];

    private array $warnings = [];

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->components->info('Running in DRY-RUN mode - no data will be written');
        }

        if (! $this->option('force') && ! $this->dryRun) {
            if (! $this->confirm('This will backfill notification configuration data. Continue?')) {
                return self::SUCCESS;
            }
        }

        try {
            if ($this->dryRun) {
                $this->runBackfill();
            } else {
                DB::transaction(fn () => $this->runBackfill());
            }

            $this->displaySummary();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Backfill failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function runBackfill(): void
    {
        $landlordIds = $this->findLandlordsWithNotificationSettings();

        if ($landlordIds->isEmpty()) {
            $this->components->warn('No landlords with notification settings found');

            return;
        }

        $this->components->info("Found {$landlordIds->count()} landlords with notification settings");

        $this->withProgressBar($landlordIds, function (int $landlordId) {
            $this->migrateSmsProvider($landlordId);
            $this->migrateWhatsAppProvider($landlordId);
            $this->migrateNotificationDefaults($landlordId);
        });

        $this->newLine();

        $this->updateTenantPreferences();
    }

    private function findLandlordsWithNotificationSettings()
    {
        return Setting::withoutGlobalScope('landlord')
            ->where('category', 'notifications')
            ->whereNotNull('landlord_id')
            ->distinct()
            ->pluck('landlord_id');
    }

    private function migrateSmsProvider(int $landlordId): void
    {
        $settings = $this->getSettingsForLandlord($landlordId);

        $smsProvider = $settings['sms_provider'] ?? null;

        if (! $smsProvider || $smsProvider === 'none') {
            return;
        }

        $credentials = match ($smsProvider) {
            'twilio' => $this->buildTwilioCredentials($settings, $landlordId),
            'africas_talking' => $this->buildAfricasTalkingCredentials($settings, $landlordId),
            default => null,
        };

        if (! $credentials) {
            $this->warnings[] = "Landlord #{$landlordId}: Unknown SMS provider '{$smsProvider}'";

            return;
        }

        $rateLimits = [
            'rate_limit_hourly' => (int) ($settings['notification_rate_limit_hourly'] ?? 100),
            'rate_limit_daily' => (int) ($settings['notification_rate_limit_daily'] ?? 500),
        ];

        $isConfigured = $this->validateSmsCredentials($smsProvider, $credentials);

        if (! $isConfigured) {
            $this->warnings[] = "Landlord #{$landlordId}: Incomplete SMS credentials (marked as disabled)";
        }

        if (! $this->dryRun) {
            NotificationProviderConfig::withoutGlobalScope('landlord')->updateOrCreate(
                [
                    'landlord_id' => $landlordId,
                    'provider_type' => NotificationProviderConfig::TYPE_SMS,
                ],
                [
                    'provider_name' => $smsProvider,
                    'credentials' => $credentials,
                    'is_enabled' => $isConfigured,
                    'is_verified' => false,
                    'settings' => $rateLimits,
                ]
            );
        }

        $this->stats['sms_migrated']++;
    }

    private function migrateWhatsAppProvider(int $landlordId): void
    {
        $settings = $this->getSettingsForLandlord($landlordId);

        $whatsappNumber = $settings['twilio_whatsapp_number'] ?? null;

        if (! $whatsappNumber) {
            return;
        }

        $credentials = [
            'account_sid' => $settings['twilio_account_sid'] ?? null,
            'auth_token' => $settings['twilio_auth_token'] ?? null,
            'whatsapp_number' => $whatsappNumber,
        ];

        $templates = $this->collectWhatsAppTemplates($settings);

        $isConfigured = ! empty($credentials['account_sid']) && ! empty($credentials['auth_token']);

        if (! $isConfigured) {
            $this->warnings[] = "Landlord #{$landlordId}: Missing Twilio credentials for WhatsApp (marked as disabled)";
        }

        if (empty($templates)) {
            $this->warnings[] = "Landlord #{$landlordId}: No WhatsApp templates found";
        }

        if (! $this->dryRun) {
            NotificationProviderConfig::withoutGlobalScope('landlord')->updateOrCreate(
                [
                    'landlord_id' => $landlordId,
                    'provider_type' => NotificationProviderConfig::TYPE_WHATSAPP,
                ],
                [
                    'provider_name' => 'twilio',
                    'credentials' => $credentials,
                    'is_enabled' => $isConfigured,
                    'is_verified' => false,
                    'settings' => ['templates' => $templates],
                ]
            );
        }

        $this->stats['whatsapp_migrated']++;
    }

    private function migrateNotificationDefaults(int $landlordId): void
    {
        $preference = NotificationPreference::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('user_id', $landlordId)
            ->first();

        $settings = $this->getSettingsForLandlord($landlordId);

        if (! $preference && empty($settings)) {
            return;
        }

        $channels = [];
        if ($preference) {
            if ($preference->email_enabled) {
                $channels[] = 'email';
            }
            if ($preference->sms_enabled) {
                $channels[] = 'sms';
            }
            if ($preference->whatsapp_enabled) {
                $channels[] = 'whatsapp';
            }
            if ($preference->push_enabled) {
                $channels[] = 'push';
            }
            if ($preference->in_app_enabled) {
                $channels[] = 'in_app';
            }
        }

        $typeSettings = $preference ? [
            'rent_reminder' => (bool) $preference->rent_reminder_enabled,
            'arrears_notice' => (bool) $preference->arrears_notice_enabled,
            'invoice' => (bool) $preference->invoice_enabled,
            'receipt' => (bool) $preference->receipt_enabled,
            'rent_hike' => (bool) $preference->rent_hike_enabled,
            'lease_expiry' => (bool) $preference->lease_expiry_enabled,
            'lease_renewal' => (bool) $preference->lease_renewal_enabled,
            'maintenance_notice' => (bool) $preference->maintenance_notice_enabled,
            'general' => (bool) $preference->general_enabled,
            'eviction_notice' => (bool) $preference->eviction_notice_enabled,
            'caretaker_invitation' => (bool) $preference->caretaker_invitation_enabled,
            'tenant_invitation' => (bool) $preference->tenant_invitation_enabled,
        ] : NotificationDefaults::DEFAULT_TYPE_SETTINGS;

        if (empty($channels)) {
            $channels = NotificationDefaults::DEFAULT_CHANNELS;
        }

        $defaults = NotificationDefaults::getDefaultAttributes();

        if (! $this->dryRun) {
            NotificationDefaults::withoutGlobalScope('landlord')->updateOrCreate(
                ['landlord_id' => $landlordId],
                [
                    'default_channels' => $channels,
                    'type_settings' => $typeSettings,
                    'reminder_days_before_due' => $preference?->rent_reminder_days_before ?? (int) ($settings['notification_default_reminder_days'] ?? $defaults['reminder_days_before_due']),
                    'quiet_hours_enabled' => $preference?->quiet_hours_enabled ?? filter_var($settings['notification_default_quiet_hours_enabled'] ?? $defaults['quiet_hours_enabled'], FILTER_VALIDATE_BOOLEAN),
                    'quiet_hours_start' => $preference?->quiet_hours_start ?? ($settings['notification_default_quiet_hours_start'] ?? $defaults['quiet_hours_start']),
                    'quiet_hours_end' => $preference?->quiet_hours_end ?? ($settings['notification_default_quiet_hours_end'] ?? $defaults['quiet_hours_end']),
                    'quiet_hours_queue_notifications' => filter_var($settings['notification_default_quiet_hours_queue'] ?? $defaults['quiet_hours_queue_notifications'], FILTER_VALIDATE_BOOLEAN),
                    'max_retries' => (int) ($settings['notification_default_max_retries'] ?? $defaults['max_retries']),
                    'retry_delay_minutes' => (int) ($settings['notification_default_retry_delay_minutes'] ?? $defaults['retry_delay_minutes']),
                    'daily_limit_per_tenant' => (int) ($settings['notification_default_daily_limit'] ?? $defaults['daily_limit_per_tenant']),
                    'hourly_limit_per_tenant' => (int) ($settings['notification_default_hourly_limit'] ?? $defaults['hourly_limit_per_tenant']),
                    'sender_name' => $settings['notification_default_sender_name'] ?? $defaults['sender_name'],
                    'reply_to_email' => $settings['notification_default_reply_to_email'] ?? $defaults['reply_to_email'],
                    'archive_days' => (int) ($settings['notification_default_archive_days'] ?? $defaults['archive_days']),
                    'track_read_status' => filter_var($settings['notification_default_track_read_status'] ?? $defaults['track_read_status'], FILTER_VALIDATE_BOOLEAN),
                ]
            );
        }

        $this->stats['defaults_created']++;
    }

    private function updateTenantPreferences(): void
    {
        $this->components->task('Updating tenant preferences to use landlord defaults', function () {
            $tenantPreferenceIds = NotificationPreference::withoutGlobalScope('landlord')
                ->whereColumn('user_id', '!=', 'landlord_id')
                ->pluck('id');

            if ($tenantPreferenceIds->isEmpty()) {
                return true;
            }

            if (! $this->dryRun) {
                NotificationPreference::withoutGlobalScope('landlord')
                    ->whereIn('id', $tenantPreferenceIds)
                    ->update(['uses_landlord_defaults' => true]);
            }

            $this->stats['preferences_updated'] = $tenantPreferenceIds->count();

            return true;
        });
    }

    private function getSettingsForLandlord(int $landlordId): array
    {
        return Setting::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('category', 'notifications')
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    private function buildTwilioCredentials(array $settings, int $landlordId): array
    {
        return [
            'account_sid' => $settings['twilio_account_sid'] ?? null,
            'auth_token' => $settings['twilio_auth_token'] ?? null,
            'phone_number' => $settings['twilio_phone_number'] ?? null,
        ];
    }

    private function buildAfricasTalkingCredentials(array $settings, int $landlordId): array
    {
        return [
            'api_key' => $settings['africas_talking_api_key'] ?? null,
            'username' => $settings['africas_talking_username'] ?? null,
            'from' => $settings['africas_talking_from'] ?? null,
        ];
    }

    private function collectWhatsAppTemplates(array $settings): array
    {
        $templates = [];

        foreach ($settings as $key => $value) {
            if (str_starts_with($key, 'whatsapp_template_') && str_ends_with($key, '_sid') && $value) {
                $type = str_replace(['whatsapp_template_', '_sid'], '', $key);
                $templates[$type] = $value;
            }
        }

        return $templates;
    }

    private function validateSmsCredentials(string $provider, array $credentials): bool
    {
        return match ($provider) {
            'twilio' => ! empty($credentials['account_sid']) &&
                        ! empty($credentials['auth_token']) &&
                        ! empty($credentials['phone_number']),
            'africas_talking' => ! empty($credentials['api_key']) &&
                                 ! empty($credentials['username']),
            default => false,
        };
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->components->info('Notification Configuration Backfill Summary');
        $this->components->twoColumnDetail('SMS Providers Migrated', (string) $this->stats['sms_migrated']);
        $this->components->twoColumnDetail('WhatsApp Providers Migrated', (string) $this->stats['whatsapp_migrated']);
        $this->components->twoColumnDetail('Landlord Defaults Created', (string) $this->stats['defaults_created']);
        $this->components->twoColumnDetail('Tenant Preferences Updated', (string) $this->stats['preferences_updated']);

        if (! empty($this->warnings)) {
            $this->newLine();
            $this->components->warn('Warnings:');
            foreach ($this->warnings as $warning) {
                $this->components->bulletList([$warning]);
            }
        }

        if ($this->dryRun) {
            $this->newLine();
            $this->components->info('DRY-RUN: No changes were made. Run without --dry-run to apply changes.');
        }
    }
}
