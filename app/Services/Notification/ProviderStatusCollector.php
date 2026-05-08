<?php

namespace App\Services\Notification;

use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Services\PushNotificationService;

class ProviderStatusCollector
{
    public function __construct(
        private NotificationConfigRepositoryInterface $configRepository,
        private PushNotificationService $pushService
    ) {}

    public function collect(int $landlordId): array
    {
        $smsProvider = $this->configRepository->getSmsProvider($landlordId);
        $twilioCredentials = $this->configRepository->getTwilioCredentials($landlordId);
        $atCredentials = $this->configRepository->getAfricasTalkingCredentials($landlordId);
        $whatsappNumber = $this->configRepository->getWhatsAppNumber($landlordId);

        return [
            'email' => [
                'configured' => true,
                'provider' => 'Laravel Mail',
            ],
            'sms' => [
                'configured' => $smsProvider !== 'none',
                'provider' => $smsProvider,
                'has_credentials' => match ($smsProvider) {
                    'twilio' => ! empty($twilioCredentials['account_sid']) && ! empty($twilioCredentials['auth_token']),
                    'africas_talking' => ! empty($atCredentials['api_key']) && ! empty($atCredentials['username']),
                    default => false,
                },
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
    }

    public function getCurrentSmsProvider(int $landlordId): string
    {
        return $this->configRepository->getSmsProvider($landlordId);
    }

    public static function getSmsProviderOptions(): array
    {
        return [
            ['value' => 'none', 'label' => 'None (Disabled)'],
            ['value' => 'twilio', 'label' => 'Twilio'],
            ['value' => 'africas_talking', 'label' => "Africa's Talking"],
        ];
    }
}
