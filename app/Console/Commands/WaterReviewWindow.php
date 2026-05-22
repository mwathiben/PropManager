<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\TenantActivity;
use App\Models\WaterReading;
use App\Services\NotificationService;
use App\Services\Water\WaterReadingCycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-88 WATER-READING-CYCLE — the safety. Each day: nudge the landlord to
 * review buildings that still have pending readings, and AUTO-APPROVE any reading
 * left pending past the review window so water revenue is never silently dropped
 * (a pending reading is excluded from invoicing and would otherwise hang forever).
 */
class WaterReviewWindow extends Command
{
    protected $signature = 'water:review-window {--dry-run}';

    protected $description = 'Remind landlords to review water readings and auto-approve any left pending past the review window';

    public function handle(WaterReadingCycleService $cycle, NotificationService $notifications): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $autoApprovedByLandlord = [];
        $reminded = 0;

        foreach ($cycle->consumptionBuildings() as $building) {
            $reviewDays = $cycle->effectiveConfig($building)['review_days'];
            $cutoff = $now->copy()->subDays($reviewDays);

            $unitIds = $building->units()->withoutGlobalScope('landlord')->pluck('id');
            if ($unitIds->isEmpty()) {
                continue;
            }

            $pending = WaterReading::withoutGlobalScope('landlord')
                ->whereIn('unit_id', $unitIds)
                ->where('status', 'pending')
                ->where('is_invoiced', false)
                ->get();

            if ($pending->isEmpty()) {
                continue;
            }

            [$overdue, $withinWindow] = $pending->partition(fn (WaterReading $r) => $r->created_at <= $cutoff);

            // Auto-approve overdue readings so they bill.
            foreach ($overdue as $reading) {
                if ($dryRun) {
                    $autoApprovedByLandlord[$building->landlord_id] = ($autoApprovedByLandlord[$building->landlord_id] ?? 0) + 1;

                    continue;
                }
                $reading->autoApprove();
                $this->auditAutoApproval($building->landlord_id, $reading);
                $autoApprovedByLandlord[$building->landlord_id] = ($autoApprovedByLandlord[$building->landlord_id] ?? 0) + 1;
            }

            // Nudge the landlord to review readings still inside the window
            // (once per building + month).
            if ($withinWindow->isNotEmpty()) {
                $key = sprintf('water-review-due:%d:%s', $building->id, $now->format('Y-m'));
                if (Cache::add($key, true, $now->copy()->addDays(40))) {
                    $reminded++;
                    if (! $dryRun) {
                        $notifications->send(
                            recipientId: (int) $building->landlord_id,
                            type: Notification::TYPE_WATER_REVIEW_DUE,
                            subject: __('water.notify.review_due_subject'),
                            message: __('water.notify.review_due_body', ['building' => $building->name, 'count' => $withinWindow->count()]),
                            data: ['building_id' => $building->id, 'pending' => $withinWindow->count()],
                            landlordId: (int) $building->landlord_id,
                        );
                    }
                }
            }
        }

        // One auto-approval escalation per landlord.
        foreach ($autoApprovedByLandlord as $landlordId => $count) {
            if (! $dryRun) {
                $notifications->send(
                    recipientId: (int) $landlordId,
                    type: Notification::TYPE_WATER_REVIEW_DUE,
                    subject: __('water.notify.auto_approved_subject'),
                    message: __('water.notify.auto_approved_body', ['count' => $count]),
                    data: ['auto_approved' => $count],
                    landlordId: (int) $landlordId,
                );
            }
        }

        $autoApproved = array_sum($autoApprovedByLandlord);
        $this->info("water:review-window: {$autoApproved} auto-approved, {$reminded} review reminder(s)");

        return self::SUCCESS;
    }

    private function auditAutoApproval(int $landlordId, WaterReading $reading): void
    {
        $tenantId = $reading->unit?->activeLease?->tenant_id;
        if (! $tenantId) {
            return;
        }

        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenantId,
            'type' => TenantActivity::TYPE_WATER_READING_AUTO_APPROVED,
            'description' => "Water reading #{$reading->id} auto-approved after the review window closed.",
            'metadata' => [
                'reading_id' => $reading->id,
                'unit_id' => $reading->unit_id,
                'consumption' => $reading->consumption,
            ],
            'performed_by' => null,
        ]);
    }
}
