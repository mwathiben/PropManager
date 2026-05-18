<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LeasePause;
use App\Services\Lease\LeasePauseService;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-61 PAUSE-2: finds active lease pauses past their pause_end
 * and resumes the lease. Runs daily 06:00 Africa/Nairobi.
 */
class LeasePauseAutoResume extends Command
{
    protected $signature = 'lease-pause:auto-resume';

    protected $description = 'Phase-61 PAUSE-2: resume leases whose pause window has elapsed.';

    public function handle(LeasePauseService $service, MetricsService $metrics): int
    {
        $resumed = 0;

        DB::transaction(function () use ($service, &$resumed) {
            $pauses = LeasePause::query()
                ->where('status', LeasePause::STATUS_ACTIVE)
                ->whereDate('pause_end', '<', now()->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($pauses as $pause) {
                $service->autoResume($pause);
                $resumed++;
            }
        });

        $metrics->gauge('lease_pause_resumed_count', $resumed);
        $this->info("lease_pause_auto_resume resumed={$resumed}");

        return self::SUCCESS;
    }
}
