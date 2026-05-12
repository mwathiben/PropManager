<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SecurityIncident;
use App\Services\KenyaDpaService;
use Illuminate\Console\Command;

/**
 * Phase-13 BREACH-4: ops path for dispatching the Article 34 /
 * Section 43(2) affected-subject notification. The decision to
 * notify subjects is an operator-judgement call ("is this breach
 * likely to result in high risk?") and not every regulator-reported
 * incident triggers this — that's why notifyAffectedSubjects is not
 * called automatically from initiateBreachNotification.
 *
 * Usage:
 *   php artisan dpa:notify-affected-subjects \
 *     --incident=42 \
 *     --user-ids=12,34,56 \
 *     --confirm
 *
 * --user-ids may also be a path prefixed with @ to read newline- or
 * comma-separated ids from disk (useful when the affected set comes
 * from a SQL extract).
 */
class NotifyAffectedSubjects extends Command
{
    protected $signature = 'dpa:notify-affected-subjects
        {--incident= : SecurityIncident id (integer, required)}
        {--user-ids= : Comma-separated user ids, or @path/to/file}
        {--confirm : Required to actually queue the mailables}';

    protected $description = 'Dispatch Article 34 / Kenya DPA Section 43(2) affected-subject notifications for a breach.';

    public function handle(KenyaDpaService $dpa): int
    {
        $incidentId = (int) ($this->option('incident') ?? 0);
        if ($incidentId <= 0) {
            $this->error('--incident is required (positive integer SecurityIncident id).');

            return self::INVALID;
        }

        $incident = SecurityIncident::find($incidentId);
        if (! $incident) {
            $this->error("SecurityIncident id={$incidentId} not found.");

            return self::FAILURE;
        }

        $userIds = $this->parseUserIds((string) ($this->option('user-ids') ?? ''));
        if (empty($userIds)) {
            $this->error('--user-ids resolved to an empty list.');

            return self::INVALID;
        }

        if (! $this->option('confirm')) {
            $this->warn('DRY RUN — pass --confirm to dispatch.');
            $this->line("Incident:    #{$incidentId} ({$incident->severity})");
            $this->line('Subjects:    '.count($userIds));
            $this->line('First 10:    '.implode(', ', array_slice($userIds, 0, 10)));

            return self::SUCCESS;
        }

        $queued = $dpa->notifyAffectedSubjects($incident, $userIds);

        $this->info("Queued {$queued} affected-subject notifications for incident #{$incidentId}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int>
     */
    private function parseUserIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        if (str_starts_with($raw, '@')) {
            $path = substr($raw, 1);
            if (! is_file($path) || ! is_readable($path)) {
                $this->error("--user-ids file not readable: {$path}");

                return [];
            }
            $raw = (string) file_get_contents($path);
        }

        $tokens = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map('intval', $tokens)));
    }
}
