<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationPreference;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-35 PLATFORM-NOTIF-3: catches users who somehow ended up
 * with zero channels enabled for transactional notification types
 * (invoice + receipt). That's a misconfiguration the
 * Phase-35 NOTIF-2 endpoint shouldn't allow, but a stale rollback
 * or seeder bug could create it. Weekly audit + gauge.
 */
class NotificationPreferenceDriftAudit extends Command
{
    private const TRANSACTIONAL_TYPES = ['invoice', 'receipt'];

    private const CHANNELS = ['email', 'sms', 'whatsapp', 'push', 'in_app'];

    protected $signature = 'notifications:preference-drift-audit';

    protected $description = 'Phase-35 PLATFORM-NOTIF-3: catch users with zero channels on transactional types.';

    public function handle(MetricsService $metrics): int
    {
        $drifted = 0;
        NotificationPreference::query()->withoutGlobalScopes()->chunk(200, function ($prefs) use (&$drifted) {
            foreach ($prefs as $pref) {
                if ($this->isDrifted($pref)) {
                    $drifted++;
                }
            }
        });

        $metrics->gauge('notification_preference_drift_count', (float) $drifted);
        $this->info(sprintf('Audited preference rows. drifted=%d', $drifted));

        return self::SUCCESS;
    }

    private function isDrifted(NotificationPreference $pref): bool
    {
        $anyChannelEnabled = false;
        foreach (self::CHANNELS as $channel) {
            if ((bool) ($pref->{$channel.'_enabled'} ?? false)) {
                $anyChannelEnabled = true;
                break;
            }
        }
        if (! $anyChannelEnabled) {
            return true;
        }

        foreach (self::TRANSACTIONAL_TYPES as $type) {
            if (! (bool) ($pref->{$type.'_enabled'} ?? false)) {
                return true;
            }
        }

        return false;
    }
}
