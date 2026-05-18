<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Models\Lease;
use App\Models\LeasePause;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase-61 PAUSE-2: temporary lease pause workflow.
 *
 *   active → completed (via cron when pause_end < now)
 *          ↘ cancelled (manual early-end)
 */
class LeasePauseService
{
    public function __construct(
        private readonly NoticePeriodValidator $notice,
    ) {}

    public function start(Lease $lease, User $initiator, array $payload): LeasePause
    {
        $pauseStart = CarbonImmutable::parse($payload['pause_start']);
        $this->notice->validate('pause', $pauseStart);

        return DB::transaction(function () use ($lease, $initiator, $pauseStart, $payload) {
            $pause = LeasePause::create([
                'lease_id' => $lease->id,
                'landlord_id' => $lease->landlord_id,
                'initiated_by' => $initiator->id,
                'pause_start' => $pauseStart->toDateString(),
                'pause_end' => $payload['pause_end'],
                'reason' => $payload['reason'],
                'reason_text' => $payload['reason_text'] ?? null,
                'status' => LeasePause::STATUS_ACTIVE,
            ]);

            $lease->is_active = false;
            $lease->save();

            return $pause;
        });
    }

    public function cancel(LeasePause $pause): LeasePause
    {
        return DB::transaction(function () use ($pause) {
            $pause->status = LeasePause::STATUS_CANCELLED;
            $pause->save();

            $lease = $pause->lease;
            $lease->is_active = true;
            $lease->save();

            return $pause;
        });
    }

    public function autoResume(LeasePause $pause): LeasePause
    {
        return DB::transaction(function () use ($pause) {
            $pause->status = LeasePause::STATUS_COMPLETED;
            $pause->auto_resumed = true;
            $pause->save();

            $lease = $pause->lease;
            $lease->is_active = true;
            $lease->save();

            return $pause;
        });
    }
}
