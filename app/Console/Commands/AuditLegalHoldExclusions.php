<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Support\LegalHoldRegistry;
use Illuminate\Console\Command;

/**
 * Phase-65 RETENTION-INTEGRATION-3: single pane of glass for ops.
 * Tallies active hold counts across every ALLOWED_HOLDABLE_TYPES so
 * a sustained spike has ONE source of truth (instead of correlating
 * messages_legal_hold_count + files_retention_held_count separately).
 *
 * Sustained high count = check why holds aren't being released after
 * litigation resolves.
 */
class AuditLegalHoldExclusions extends Command
{
    protected $signature = 'legal-hold:audit-exclusions';

    protected $description = 'Emit retention_legal_hold_exclusions_count gauges per ALLOWED_HOLDABLE_TYPES';

    public function handle(MetricsService $metrics): int
    {
        foreach (LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES as $subjectClass) {
            $count = count(LegalHoldRegistry::heldIdsFor($subjectClass));

            $metrics->gauge(
                'retention_legal_hold_exclusions_count',
                $count,
                ['subject_type' => class_basename($subjectClass)],
            );

            $this->info("{$subjectClass}: {$count} active holds");
        }

        return self::SUCCESS;
    }
}
