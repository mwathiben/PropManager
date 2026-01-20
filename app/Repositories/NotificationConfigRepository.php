<?php

namespace App\Repositories;

use App\Models\NotificationProviderConfig;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use InvalidArgumentException;

class NotificationConfigRepository implements NotificationConfigRepositoryInterface
{
    private const DEFAULT_RATE_LIMIT_HOURLY = 100;

    private const DEFAULT_RATE_LIMIT_DAILY = 1000;

    public function getSmsProvider(int $landlordId): string
    {
        $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);

        return $config?->provider_name ?? 'none';
    }

    public function getTwilioCredentials(int $landlordId): array
    {
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

    public function getAfricasTalkingCredentials(int $landlordId): array
    {
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

    public function getWhatsAppNumber(int $landlordId): ?string
    {
        $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);

        return $config?->getCredential('whatsapp_number');
    }

    public function getWhatsAppTemplateSid(int $landlordId, string $type): ?string
    {
        $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);
        $templates = $config?->getSetting('templates', []);

        return $templates[$type] ?? null;
    }

    public function getRateLimits(int $landlordId): array
    {
        $smsConfig = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);

        return [
            'hourly' => $smsConfig?->getSetting('rate_limit_hourly', self::DEFAULT_RATE_LIMIT_HOURLY) ?? self::DEFAULT_RATE_LIMIT_HOURLY,
            'daily' => $smsConfig?->getSetting('rate_limit_daily', self::DEFAULT_RATE_LIMIT_DAILY) ?? self::DEFAULT_RATE_LIMIT_DAILY,
        ];
    }

    public function setSmsProvider(int $landlordId, string $provider): void
    {
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $config->update(['provider_name' => $provider]);
    }

    public function setTwilioCredentials(int $landlordId, array $credentials): void
    {
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $existingCreds = $config->credentials ?? [];
        $existingProvider = $config->provider_name;

        $newCreds = array_filter([
            'account_sid' => $credentials['account_sid'] ?? null,
            'auth_token' => $credentials['auth_token'] ?? null,
            'phone_number' => $credentials['phone_number'] ?? null,
        ], fn ($v) => $v !== null);

        // If provider changed, replace credentials entirely; otherwise merge for partial updates
        $finalCreds = ($existingProvider !== null && $existingProvider !== 'twilio')
            ? $newCreds
            : array_merge($existingCreds, $newCreds);

        $config->update([
            'provider_name' => 'twilio',
            'credentials' => $finalCreds,
        ]);
    }

    public function setAfricasTalkingCredentials(int $landlordId, array $credentials): void
    {
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $existingCreds = $config->credentials ?? [];
        $existingProvider = $config->provider_name;

        $newCreds = array_filter([
            'api_key' => $credentials['api_key'] ?? null,
            'username' => $credentials['username'] ?? null,
            'from' => $credentials['from'] ?? null,
        ], fn ($v) => $v !== null);

        // If provider changed, replace credentials entirely; otherwise merge for partial updates
        $finalCreds = ($existingProvider !== null && $existingProvider !== 'africas_talking')
            ? $newCreds
            : array_merge($existingCreds, $newCreds);

        $config->update([
            'provider_name' => 'africas_talking',
            'credentials' => $finalCreds,
        ]);
    }

    public function setWhatsAppNumber(int $landlordId, string $number): void
    {
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);

        // Get Twilio credentials from SMS config for WhatsApp (they share Twilio account)
        $smsConfig = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);
        $existingCreds = $config->credentials ?? [];

        // Start with existing credentials and only overwrite with non-null values
        $mergedCreds = $existingCreds;
        $mergedCreds['whatsapp_number'] = $number;

        // Only copy account_sid and auth_token if they are non-null to preserve existing values
        $accountSid = $smsConfig?->getCredential('account_sid');
        $authToken = $smsConfig?->getCredential('auth_token');

        if ($accountSid !== null) {
            $mergedCreds['account_sid'] = $accountSid;
        }
        if ($authToken !== null) {
            $mergedCreds['auth_token'] = $authToken;
        }

        $config->update([
            'provider_name' => 'twilio',
            'credentials' => $mergedCreds,
        ]);
    }

    public function setWhatsAppTemplateSid(int $landlordId, string $type, string $sid): void
    {
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_WHATSAPP);
        $templates = $config->getSetting('templates', []);
        $templates[$type] = $sid;
        $config->update([
            'settings' => array_merge($config->settings ?? [], ['templates' => $templates]),
        ]);
    }

    public function getEmailCredentials(int $landlordId): array
    {
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

    public function setEmailCredentials(int $landlordId, array $credentials): void
    {
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_EMAIL);
        $existingCreds = $config->credentials ?? [];

        $newCreds = array_filter([
            'mailer' => $credentials['mailer'] ?? null,
            'host' => $credentials['host'] ?? null,
            'port' => $credentials['port'] ?? null,
            'username' => $credentials['username'] ?? null,
            'password' => $credentials['password'] ?? null,
            'encryption' => $credentials['encryption'] ?? null,
            'from_address' => $credentials['from_address'] ?? null,
            'from_name' => $credentials['from_name'] ?? null,
        ], fn ($v) => $v !== null);

        $updateData = ['credentials' => array_merge($existingCreds, $newCreds)];

        if (isset($credentials['mailer'])) {
            $updateData['provider_name'] = $credentials['mailer'];
        }
        if (isset($credentials['enabled'])) {
            $updateData['is_enabled'] = $credentials['enabled'];
        }

        $config->update($updateData);
    }

    public function isEmailEnabled(int $landlordId): bool
    {
        $config = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_EMAIL);

        return $config?->is_enabled ?? true;
    }

    public function isSetupComplete(int $landlordId): bool
    {
        $smsConfig = NotificationProviderConfig::forLandlord($landlordId, NotificationProviderConfig::TYPE_SMS);

        return $smsConfig?->getSetting('setup_complete', false) ?? false;
    }

    public function markSetupComplete(int $landlordId): void
    {
        $config = NotificationProviderConfig::getOrCreate($landlordId, NotificationProviderConfig::TYPE_SMS);
        $config->update([
            'settings' => array_merge($config->settings ?? [], ['setup_complete' => true]),
        ]);
    }

    public function isProviderConfigured(int $landlordId, string $providerType): bool
    {
        $typeMap = [
            'sms' => NotificationProviderConfig::TYPE_SMS,
            'whatsapp' => NotificationProviderConfig::TYPE_WHATSAPP,
            'email' => NotificationProviderConfig::TYPE_EMAIL,
            'push' => NotificationProviderConfig::TYPE_PUSH,
        ];

        if (! array_key_exists($providerType, $typeMap)) {
            $validTypes = implode(', ', array_keys($typeMap));
            throw new InvalidArgumentException(
                "Invalid provider type '{$providerType}'. Valid types are: {$validTypes}."
            );
        }

        $type = $typeMap[$providerType];
        $config = NotificationProviderConfig::forLandlord($landlordId, $type);

        return $config?->isConfigured() ?? false;
    }
}
