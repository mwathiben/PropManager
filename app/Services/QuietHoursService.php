<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\ValueObjects\QuietHoursConfig;
use Carbon\Carbon;

class QuietHoursService
{
    public function isQuietHours(QuietHoursConfig $config, ?Carbon $now = null): bool
    {
        if (! $config->enabled) {
            return false;
        }

        $now ??= Carbon::now($config->timezone);

        $start = Carbon::parse($config->start, $config->timezone);
        $end = Carbon::parse($config->end, $config->timezone);

        $start->setDate($now->year, $now->month, $now->day);
        $end->setDate($now->year, $now->month, $now->day);

        if ($end->lessThan($start)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->between($start, $end);
    }

    public function shouldDefer(QuietHoursConfig $config, string $urgency): bool
    {
        if ($this->canBypassQuietHours($urgency)) {
            return false;
        }

        return $this->isQuietHours($config);
    }

    public function getNextDeliveryTime(QuietHoursConfig $config): Carbon
    {
        $now = Carbon::now($config->timezone);
        $end = Carbon::parse($config->end, $config->timezone);
        $end->setDate($now->year, $now->month, $now->day);

        if ($end->lessThanOrEqualTo($now)) {
            $end->addDay();
        }

        return $end;
    }

    public function canBypassQuietHours(string $urgency): bool
    {
        return in_array($urgency, [
            Notification::URGENCY_CRITICAL,
            Notification::URGENCY_URGENT,
        ], true);
    }

    public function getConfigForUser(User $user, int $landlordId): QuietHoursConfig
    {
        $preference = NotificationPreference::getOrCreate($user->id, $landlordId);
        $timezone = $user->getTimezone();

        return QuietHoursConfig::fromPreference($preference, $timezone);
    }
}
