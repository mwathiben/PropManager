<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\Water\WaterReadingCycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-88 WATER-READING-CYCLE: on each building's configured reading day, remind
 * its caretaker to take water readings. Idempotent per building + month.
 */
class WaterReadingReminders extends Command
{
    protected $signature = 'water:reading-reminders {--dry-run}';

    protected $description = 'Remind caretakers to take water readings on the configured reading day';

    public function handle(WaterReadingCycleService $cycle, NotificationService $notifications): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = (int) now()->day;
        $sent = 0;

        foreach ($cycle->consumptionBuildingsWithCaretaker() as $building) {
            $cfg = $cycle->effectiveConfig($building);
            if ($cfg['reading_day'] === null || (int) $cfg['reading_day'] !== $today) {
                continue;
            }

            $key = sprintf('water-reading-due:%d:%s', $building->id, now()->format('Y-m'));
            if (! Cache::add($key, true, now()->addDays(40))) {
                continue;
            }

            if ($dryRun) {
                $sent++;

                continue;
            }

            $notifications->send(
                recipientId: (int) $building->caretaker_id,
                type: Notification::TYPE_WATER_READING_DUE,
                subject: __('water.notify.reading_due_subject'),
                message: __('water.notify.reading_due_body', ['building' => $building->name]),
                data: ['building_id' => $building->id],
                landlordId: (int) $building->landlord_id,
            );
            $sent++;
        }

        $this->info("water:reading-reminders: {$sent} reminder(s) dispatched");

        return self::SUCCESS;
    }
}
