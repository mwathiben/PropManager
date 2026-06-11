<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\NotificationPreference;

/**
 * Orders notification channels by a recipient's preferences, extracted from
 * NotificationService (M2 decomposition). Pure preference logic: promote
 * WhatsApp when the recipient has a valid number, intersect with the
 * urgency-allowed set, and pick the first channel the recipient will
 * actually receive. Behaviour is locked by NotificationServiceTest and the
 * send/defer paths it exercises — a verbatim move.
 */
class ChannelPrioritizer
{
    /**
     * @param  array<int, string>  $channels
     */
    public function findPrimaryChannel(array $channels, NotificationPreference $preferences, string $type): ?string
    {
        foreach ($channels as $channel) {
            if ($preferences->canReceive($type, $channel)) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Get prioritized channel order based on user's WhatsApp availability.
     * WhatsApp is promoted to first position when user has valid whatsapp_number and whatsapp_enabled.
     *
     * @return array<int, string>
     */
    public function prioritizeChannels(NotificationPreference $preferences): array
    {
        $defaultOrder = ['email', 'sms', 'whatsapp', 'push', 'in_app'];

        $hasValidWhatsApp = $preferences->whatsapp_enabled
            && ! empty($preferences->whatsapp_number)
            && $preferences->isValidE164WhatsAppNumber();

        if ($hasValidWhatsApp) {
            return ['whatsapp', 'sms', 'email', 'push', 'in_app'];
        }

        return $defaultOrder;
    }

    /**
     * Filter and prioritize channels based on urgency and user preferences.
     *
     * @param  array<int, string>  $allowedChannels
     * @return array<int, string>
     */
    public function prioritizeChannelsWithUrgency(NotificationPreference $preferences, array $allowedChannels): array
    {
        $prioritized = $this->prioritizeChannels($preferences);

        return array_values(array_intersect($prioritized, $allowedChannels));
    }
}
