<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lease\RentEscalationService;
use Illuminate\Console\Command;

/**
 * Phase-83 RENT-ESCALATION-2: apply scheduled rent escalations whose
 * effective_date has arrived. Idempotent (status-guarded), so re-running the
 * same day is a no-op for already-applied rows.
 */
class RentApplyEscalations extends Command
{
    protected $signature = 'rent:apply-escalations';

    protected $description = 'Phase-83 RENT-ESCALATION-2: apply due scheduled rent escalations.';

    public function handle(RentEscalationService $service): int
    {
        $applied = $service->applyAllDue();

        $this->info("rent:apply-escalations: {$applied} escalation(s) applied");

        return self::SUCCESS;
    }
}
