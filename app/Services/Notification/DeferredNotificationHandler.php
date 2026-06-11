<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\QuietHoursService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Quiet-hours deferral for notifications, extracted from NotificationService
 * (M2 decomposition). Decides whether a notification must wait for quiet
 * hours to end, persists the deferred row, schedules the delayed delivery
 * job, and re-sends when the window opens. Behaviour is locked by
 * NotificationServiceTest (the defer / critical-bypass cases exercise this
 * through send()) — a verbatim move.
 */
class DeferredNotificationHandler
{
    public function __construct(
        private readonly QuietHoursService $quietHoursService,
        private readonly ChannelPrioritizer $channelPrioritizer,
        private readonly ChannelTransport $channelTransport,
    ) {}

    public function shouldDefer(string $urgency, User $recipient, int $landlordId): bool
    {
        return ! $this->canBypassQuietHours($urgency) && $this->isInQuietHours($recipient, $landlordId);
    }

    /**
     * Check if recipient is currently in quiet hours.
     */
    public function isInQuietHours(User $recipient, int $landlordId): bool
    {
        $config = $this->quietHoursService->getConfigForUser($recipient, $landlordId);

        return $this->quietHoursService->isQuietHours($config);
    }

    /**
     * Check if this urgency level can bypass quiet hours.
     * Critical and urgent notifications are never deferred.
     */
    public function canBypassQuietHours(string $urgency): bool
    {
        return $this->quietHoursService->canBypassQuietHours($urgency);
    }

    /**
     * Get the next quiet hours end time for scheduling deferred notifications.
     */
    public function getQuietHoursEndTime(User $recipient, int $landlordId): Carbon
    {
        $config = $this->quietHoursService->getConfigForUser($recipient, $landlordId);

        return $this->quietHoursService->getNextDeliveryTime($config);
    }

    /**
     * Create a deferred notification scheduled for after quiet hours.
     */
    public function createDeferredNotification(
        int $landlordId,
        int $recipientId,
        string $type,
        string $channel,
        string $subject,
        string $message,
        ?array $data,
        string $urgency,
        Carbon $scheduledFor
    ): Notification {
        return Notification::create([
            'landlord_id' => $landlordId,
            'recipient_id' => $recipientId,
            'type' => $type,
            'urgency' => $urgency,
            'channel' => $channel,
            'subject' => $subject,
            'message' => $message,
            'data' => $data,
            'status' => 'pending',
            'scheduled_for' => $scheduledFor,
            'quiet_hours_suppressed' => true,
        ]);
    }

    /**
     * Defer notification until after quiet hours end.
     *
     * @param  array<int, string>  $allowedChannels
     */
    public function defer(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data,
        int $landlordId,
        string $urgency,
        array $allowedChannels
    ): array {
        $recipient = User::findOrFail($recipientId);
        $preferences = NotificationPreference::getOrCreate($recipientId, $landlordId);
        $prioritizedChannels = $this->channelPrioritizer->prioritizeChannelsWithUrgency($preferences, $allowedChannels);
        $primaryChannel = $this->channelPrioritizer->findPrimaryChannel($prioritizedChannels, $preferences, $type);

        if (! $primaryChannel) {
            return ['error' => 'no_available_channel'];
        }

        $scheduledFor = $this->getQuietHoursEndTime($recipient, $landlordId);

        $notification = $this->createDeferredNotification(
            $landlordId,
            $recipientId,
            $type,
            $primaryChannel,
            $subject,
            $message,
            $data,
            $urgency,
            $scheduledFor
        );

        // Dispatch delayed job to send notification when quiet hours end
        SendNotificationJob::forDeferred($notification->id)
            ->delay($scheduledFor);

        Log::info('Notification deferred for quiet hours', [
            'notification_id' => $notification->id,
            'recipient_id' => $recipientId,
            'scheduled_for' => $scheduledFor->toDateTimeString(),
            'channel' => $primaryChannel,
        ]);

        return [
            $primaryChannel => 'deferred',
            'scheduled_for' => $scheduledFor->toDateTimeString(),
            'quiet_hours_suppressed' => true,
        ];
    }

    /**
     * Send a deferred notification (used by scheduler/job).
     */
    public function sendDeferred(Notification $notification): bool
    {
        if (! $notification->isScheduled() && $notification->scheduled_for?->isPast()) {
            $recipient = $notification->recipient;

            if (! $recipient) {
                $notification->markAsFailed('Recipient not found');

                return false;
            }

            try {
                $sent = $this->channelTransport->sendViaChannel($notification, $recipient);
                if ($sent) {
                    $notification->update(['scheduled_for' => null]);
                }

                return $sent;
            } catch (\Exception $e) {
                $notification->markAsFailed($e->getMessage());
                $this->channelTransport->logChannelFailure($notification, $e);

                return false;
            }
        }

        return false;
    }
}
