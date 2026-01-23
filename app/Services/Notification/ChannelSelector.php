<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\NotificationPreference;

class ChannelSelector
{
    /**
     * Channel priority order by urgency level.
     * Matches NotificationService::URGENCY_CHANNELS for consistency.
     */
    private const URGENCY_CHANNELS = [
        Notification::URGENCY_CRITICAL => [
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_SMS,
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_IN_APP,
        ],
        Notification::URGENCY_URGENT => [
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_IN_APP,
        ],
        Notification::URGENCY_IMPORTANT => [
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_IN_APP,
        ],
        Notification::URGENCY_INFORMATIONAL => [
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_IN_APP,
        ],
    ];

    /**
     * Get channels allowed for a given urgency level.
     */
    public function getChannelsForUrgency(string $urgency): array
    {
        return self::URGENCY_CHANNELS[$urgency] ?? self::URGENCY_CHANNELS[Notification::URGENCY_INFORMATIONAL];
    }

    /**
     * Find the first channel that user can receive notifications on.
     */
    public function findPrimaryChannel(
        array $channels,
        NotificationPreference $preferences,
        string $type
    ): ?string {
        foreach ($channels as $channel) {
            if ($preferences->canReceive($type, $channel)) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Filter and prioritize channels based on urgency and user preferences.
     */
    public function prioritizeForUrgency(
        NotificationPreference $preferences,
        array $allowedChannels
    ): array {
        $prioritized = $this->prioritizeByWhatsApp($preferences);

        return array_values(array_intersect($prioritized, $allowedChannels));
    }

    /**
     * Select the best channel for a notification.
     * Returns null if no channel is available.
     */
    public function selectChannel(
        string $urgency,
        string $type,
        NotificationPreference $preferences
    ): ?string {
        $allowedChannels = $this->getChannelsForUrgency($urgency);
        $prioritizedChannels = $this->prioritizeForUrgency($preferences, $allowedChannels);

        return $this->findPrimaryChannel($prioritizedChannels, $preferences, $type);
    }

    /**
     * Get prioritized channel order based on user's WhatsApp availability.
     * WhatsApp is promoted when user has valid whatsapp_number and whatsapp_enabled.
     */
    private function prioritizeByWhatsApp(NotificationPreference $preferences): array
    {
        $defaultOrder = [
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_SMS,
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_IN_APP,
        ];

        $hasValidWhatsApp = $preferences->whatsapp_enabled
            && ! empty($preferences->whatsapp_number)
            && $preferences->isValidE164WhatsAppNumber();

        if ($hasValidWhatsApp) {
            return [
                Notification::CHANNEL_WHATSAPP,
                Notification::CHANNEL_SMS,
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_PUSH,
                Notification::CHANNEL_IN_APP,
            ];
        }

        return $defaultOrder;
    }
}
