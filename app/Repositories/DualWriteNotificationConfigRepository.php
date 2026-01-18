<?php

namespace App\Repositories;

use App\Models\NotificationProviderConfig;
use App\Models\Setting;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;

class DualWriteNotificationConfigRepository implements NotificationConfigRepositoryInterface
{
    private const DEFAULT_RATE_LIMIT_HOURLY = 100;

    private const DEFAULT_RATE_LIMIT_DAILY = 1000;

    public function getSmsProvider(int $landlordId): string
    {
        if (config('features.notification_v2')) {
            $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);

            return $config?->provider_name ?? 'none';
        }

        return Setting::get('sms_provider', 'none', $landlordId);
    }

    public function getTwilioCredentials(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);
            if ($config?->provider_name === 'twilio' && $config->credentials) {
                return [
                    'account_sid' => $config->getCredential('account_sid'),
                    'auth_token' => $config->getCredential('auth_token'),
                    'phone_number' => $config->getCredential('phone_number'),
                ];
            }

            return ['account_sid' => null, 'auth_token' => null, 'phone_number' => null];
        }

        return [
            'account_sid' => Setting::get('twilio_account_sid', null, $landlordId),
            'auth_token' => Setting::get('twilio_auth_token', null, $landlordId),
            'phone_number' => Setting::get('twilio_phone_number', null, $landlordId),
        ];
    }

    public function getAfricasTalkingCredentials(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);
            if ($config?->provider_name === 'africas_talking' && $config->credentials) {
                return [
                    'api_key' => $config->getCredential('api_key'),
                    'username' => $config->getCredential('username'),
                    'from' => $config->getCredential('from'),
                ];
            }

            return ['api_key' => null, 'username' => null, 'from' => null];
        }

        return [
            'api_key' => Setting::get('africas_talking_api_key', null, $landlordId),
            'username' => Setting::get('africas_talking_username', null, $landlordId),
            'from' => Setting::get('africas_talking_from', null, $landlordId),
        ];
    }

    public function getWhatsAppNumber(int $landlordId): ?string
    {
        if (config('features.notification_v2')) {
            $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);

            return $config?->getCredential('whatsapp_number');
        }

        return Setting::get('twilio_whatsapp_number', null, $landlordId);
    }

    public function getWhatsAppTemplateSid(int $landlordId, string $type): ?string
    {
        if (config('features.notification_v2')) {
            $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);
            $templates = $config?->getSetting('templates', []);

            return $templates[$type] ?? null;
        }

        return Setting::get("whatsapp_template_{$type}_sid", null, $landlordId);
    }

    public function getRateLimits(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $smsConfig = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);

            return [
                'hourly' => $smsConfig?->getSetting('rate_limit_hourly', self::DEFAULT_RATE_LIMIT_HOURLY) ?? self::DEFAULT_RATE_LIMIT_HOURLY,
                'daily' => $smsConfig?->getSetting('rate_limit_daily', self::DEFAULT_RATE_LIMIT_DAILY) ?? self::DEFAULT_RATE_LIMIT_DAILY,
            ];
        }

        return [
            'hourly' => (int) Setting::get('notification_rate_limit_hourly', self::DEFAULT_RATE_LIMIT_HOURLY, $landlordId),
            'daily' => (int) Setting::get('notification_rate_limit_daily', self::DEFAULT_RATE_LIMIT_DAILY, $landlordId),
        ];
    }

    public function setSmsProvider(int $landlordId, string $provider): void
    {
        // Legacy Setting
        Setting::set('sms_provider', $provider, false, 'notifications', 'SMS provider selection', $landlordId);

        // New NotificationProviderConfig
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $config->update(['provider_name' => $provider]);
    }

    public function setTwilioCredentials(int $landlordId, array $credentials): void
    {
        // Legacy Setting (encrypted)
        Setting::set('twilio_account_sid', $credentials['account_sid'] ?? null, true, 'notifications', 'Twilio Account SID', $landlordId);
        Setting::set('twilio_auth_token', $credentials['auth_token'] ?? null, true, 'notifications', 'Twilio Auth Token', $landlordId);
        Setting::set('twilio_phone_number', $credentials['phone_number'] ?? null, false, 'notifications', 'Twilio SMS Phone Number', $landlordId);

        // New NotificationProviderConfig
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $config->update([
            'provider_name' => 'twilio',
            'credentials' => [
                'account_sid' => $credentials['account_sid'] ?? null,
                'auth_token' => $credentials['auth_token'] ?? null,
                'phone_number' => $credentials['phone_number'] ?? null,
            ],
        ]);
    }

    public function setAfricasTalkingCredentials(int $landlordId, array $credentials): void
    {
        // Legacy Setting (encrypted)
        Setting::set('africas_talking_api_key', $credentials['api_key'] ?? null, true, 'notifications', "Africa's Talking API Key", $landlordId);
        Setting::set('africas_talking_username', $credentials['username'] ?? null, false, 'notifications', "Africa's Talking Username", $landlordId);
        Setting::set('africas_talking_from', $credentials['from'] ?? null, false, 'notifications', "Africa's Talking Sender ID", $landlordId);

        // New NotificationProviderConfig
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $config->update([
            'provider_name' => 'africas_talking',
            'credentials' => [
                'api_key' => $credentials['api_key'] ?? null,
                'username' => $credentials['username'] ?? null,
                'from' => $credentials['from'] ?? null,
            ],
        ]);
    }

    public function setWhatsAppNumber(int $landlordId, string $number): void
    {
        // Legacy Setting
        Setting::set('twilio_whatsapp_number', $number, false, 'notifications', 'Twilio WhatsApp Number', $landlordId);

        // New NotificationProviderConfig
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);

        // Get Twilio credentials from SMS config for WhatsApp (they share Twilio account)
        $smsConfig = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);
        $existingCreds = $config->credentials ?? [];

        $config->update([
            'provider_name' => 'twilio',
            'credentials' => array_merge($existingCreds, [
                'whatsapp_number' => $number,
                'account_sid' => $smsConfig?->getCredential('account_sid'),
                'auth_token' => $smsConfig?->getCredential('auth_token'),
            ]),
        ]);
    }

    public function setWhatsAppTemplateSid(int $landlordId, string $type, string $sid): void
    {
        // Legacy Setting
        Setting::set("whatsapp_template_{$type}_sid", $sid, false, 'notifications', "WhatsApp Template SID for {$type}", $landlordId);

        // New NotificationProviderConfig
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);
        $templates = $config->getSetting('templates', []);
        $templates[$type] = $sid;
        $config->update([
            'settings' => array_merge($config->settings ?? [], ['templates' => $templates]),
        ]);
    }

    public function getEmailCredentials(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_EMAIL);
            if ($config && $config->credentials) {
                return [
                    'mailer' => $config->getCredential('mailer'),
                    'host' => $config->getCredential('host'),
                    'port' => $config->getCredential('port'),
                    'username' => $config->getCredential('username'),
                    'password' => $config->getCredential('password'),
                    'encryption' => $config->getCredential('encryption'),
                    'from_address' => $config->getCredential('from_address'),
                    'from_name' => $config->getCredential('from_name'),
                    'enabled' => $config->is_enabled,
                ];
            }

            return $this->getEmptyEmailCredentials();
        }

        return [
            'mailer' => Setting::get('mail_mailer', null, $landlordId),
            'host' => Setting::get('mail_host', null, $landlordId),
            'port' => Setting::get('mail_port', null, $landlordId),
            'username' => Setting::get('mail_username', null, $landlordId),
            'password' => Setting::get('mail_password', null, $landlordId),
            'encryption' => Setting::get('mail_encryption', null, $landlordId),
            'from_address' => Setting::get('mail_from_address', null, $landlordId),
            'from_name' => Setting::get('mail_from_name', null, $landlordId),
            'enabled' => (bool) Setting::get('email_enabled', true, $landlordId),
        ];
    }

    public function setEmailCredentials(int $landlordId, array $credentials): void
    {
        // Legacy Setting (password is encrypted)
        $emailFields = ['mailer', 'host', 'port', 'username', 'encryption', 'from_address', 'from_name'];
        foreach ($emailFields as $field) {
            if (isset($credentials[$field])) {
                Setting::set("mail_{$field}", $credentials[$field], false, 'email', ucfirst(str_replace('_', ' ', $field)), $landlordId);
            }
        }
        if (isset($credentials['password'])) {
            Setting::set('mail_password', $credentials['password'], true, 'email', 'Mail Password', $landlordId);
        }
        if (isset($credentials['enabled'])) {
            Setting::set('email_enabled', $credentials['enabled'], false, 'email', 'Email Enabled', $landlordId);
        }

        // New NotificationProviderConfig
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_EMAIL);
        $config->update([
            'provider_name' => $credentials['mailer'] ?? 'smtp',
            'credentials' => [
                'mailer' => $credentials['mailer'] ?? null,
                'host' => $credentials['host'] ?? null,
                'port' => $credentials['port'] ?? null,
                'username' => $credentials['username'] ?? null,
                'password' => $credentials['password'] ?? null,
                'encryption' => $credentials['encryption'] ?? null,
                'from_address' => $credentials['from_address'] ?? null,
                'from_name' => $credentials['from_name'] ?? null,
            ],
            'is_enabled' => $credentials['enabled'] ?? true,
        ]);
    }

    public function isEmailEnabled(int $landlordId): bool
    {
        if (config('features.notification_v2')) {
            $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_EMAIL);

            return $config?->is_enabled ?? true;
        }

        return (bool) Setting::get('email_enabled', true, $landlordId);
    }

    public function isSetupComplete(int $landlordId): bool
    {
        if (config('features.notification_v2')) {
            $smsConfig = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);

            return $smsConfig?->getSetting('setup_complete', false) ?? false;
        }

        return (bool) Setting::get('notifications_setup_complete', false, $landlordId);
    }

    public function markSetupComplete(int $landlordId): void
    {
        // Legacy Setting
        Setting::set('notifications_setup_complete', true, false, 'notifications', 'Setup Completed', $landlordId);

        // New NotificationProviderConfig - store on SMS config as the "primary" config
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $config->update([
            'settings' => array_merge($config->settings ?? [], ['setup_complete' => true]),
        ]);
    }

    public function isProviderConfigured(int $landlordId, string $providerType): bool
    {
        if (config('features.notification_v2')) {
            $typeMap = [
                'sms' => NotificationProviderConfig::TYPE_SMS,
                'whatsapp' => NotificationProviderConfig::TYPE_WHATSAPP,
                'email' => NotificationProviderConfig::TYPE_EMAIL,
                'push' => NotificationProviderConfig::TYPE_PUSH,
            ];
            $type = $typeMap[$providerType] ?? $providerType;
            $config = NotificationProviderConfig::forLandlord($landlordId, $type);

            return $config?->isConfigured() ?? false;
        }

        return match ($providerType) {
            'sms' => $this->isSmsConfiguredLegacy($landlordId),
            'whatsapp' => $this->isWhatsAppConfiguredLegacy($landlordId),
            'email' => $this->isEmailConfiguredLegacy($landlordId),
            default => false,
        };
    }

    private function getEmptyEmailCredentials(): array
    {
        return [
            'mailer' => null,
            'host' => null,
            'port' => null,
            'username' => null,
            'password' => null,
            'encryption' => null,
            'from_address' => null,
            'from_name' => null,
            'enabled' => true,
        ];
    }

    private function isSmsConfiguredLegacy(int $landlordId): bool
    {
        $provider = Setting::get('sms_provider', 'none', $landlordId);
        if ($provider === 'none') {
            return false;
        }
        if ($provider === 'twilio') {
            return ! empty(Setting::get('twilio_account_sid', null, $landlordId))
                && ! empty(Setting::get('twilio_auth_token', null, $landlordId))
                && ! empty(Setting::get('twilio_phone_number', null, $landlordId));
        }
        if ($provider === 'africas_talking') {
            return ! empty(Setting::get('africas_talking_api_key', null, $landlordId))
                && ! empty(Setting::get('africas_talking_username', null, $landlordId));
        }

        return false;
    }

    private function isWhatsAppConfiguredLegacy(int $landlordId): bool
    {
        return ! empty(Setting::get('twilio_account_sid', null, $landlordId))
            && ! empty(Setting::get('twilio_auth_token', null, $landlordId))
            && ! empty(Setting::get('twilio_whatsapp_number', null, $landlordId));
    }

    private function isEmailConfiguredLegacy(int $landlordId): bool
    {
        return ! empty(Setting::get('mail_host', null, $landlordId))
            || (bool) Setting::get('email_enabled', true, $landlordId);
    }
}
