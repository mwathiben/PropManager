<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\KenyaDpaService;
use Illuminate\Console\Command;

/**
 * Phase-13 BREACH-1: ops-during-incident path for invoking
 * KenyaDpaService::initiateBreachNotification. Before this command,
 * the only way to record a breach was to open tinker and call the
 * method by hand — under stress, with bad arguments, with no audit
 * of who triggered it. This wraps the method with input validation,
 * dry-run support, and actor resolution via ROTATED_BY (same pattern
 * as Phase-11 SECRETS-4 WebhookRotateSecret).
 *
 * Usage:
 *   php artisan dpa:initiate-breach \
 *     --description="S3 bucket misconfiguration — public listing for 6h" \
 *     --data-types=national_id,phone,email \
 *     --affected=420 \
 *     --mitigation="Bucket policy reverted; tokens rotated; affected subjects identified by lease lookup" \
 *     --confirm
 */
class InitiateBreach extends Command
{
    protected $signature = 'dpa:initiate-breach
        {--description= : Free-text breach description (required)}
        {--data-types= : Comma-separated affected data categories (e.g. national_id,phone)}
        {--affected= : Estimated count of affected data subjects (integer)}
        {--mitigation= : Free-text mitigation measures (required)}
        {--reporter= : Optional user id of the reporter (defaults to ROTATED_BY env or null)}
        {--confirm : Required to actually create the SecurityIncident}';

    protected $description = 'Initiate Kenya DPA Section 43 breach notification — wraps KenyaDpaService::initiateBreachNotification.';

    public function handle(KenyaDpaService $dpa): int
    {
        $description = trim((string) ($this->option('description') ?? ''));
        $dataTypes = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($this->option('data-types') ?? ''))
        )));
        $affected = (int) ($this->option('affected') ?? 0);
        $mitigation = trim((string) ($this->option('mitigation') ?? ''));

        if ($description === '' || $mitigation === '') {
            $this->error('--description and --mitigation are required.');

            return self::INVALID;
        }

        if ($affected < 0) {
            $this->error('--affected must be a non-negative integer.');

            return self::INVALID;
        }

        if (! $this->option('confirm')) {
            $this->warn('DRY RUN — pass --confirm to create the incident.');
            $this->line('Description:    '.$description);
            $this->line('Data types:     '.(empty($dataTypes) ? '(none)' : implode(', ', $dataTypes)));
            $this->line('Affected count: '.$affected);
            $this->line('Mitigation:     '.$mitigation);

            return self::SUCCESS;
        }

        $reporterId = $this->resolveReporter();

        $incident = $dpa->initiateBreachNotification(
            breachDescription: $description,
            affectedDataTypes: $dataTypes,
            estimatedAffectedUsers: $affected,
            mitigationMeasures: $mitigation,
            reportedBy: $reporterId,
        );

        $this->info("SecurityIncident #{$incident->id} created.");
        $this->line('Severity:           '.$incident->severity);
        $this->line('Notification by:    '.$incident->notification_deadline?->toDateTimeString());
        $this->line('ODPC email target:  '.(config('security.kenya_dpa.odpc_email') ?: '(unset)'));
        $this->line('Operator alert to:  '.(config('security.kenya_dpa.breach_notification_email') ?: '(unset — set KENYA_DPA_BREACH_EMAIL)'));

        return self::SUCCESS;
    }

    private function resolveReporter(): ?int
    {
        $optionReporter = $this->option('reporter');
        if ($optionReporter !== null && $optionReporter !== '') {
            return (int) $optionReporter;
        }

        $email = env('ROTATED_BY');
        if (! $email) {
            return null;
        }

        $user = User::where('email', $email)->first();

        return $user?->id;
    }
}
