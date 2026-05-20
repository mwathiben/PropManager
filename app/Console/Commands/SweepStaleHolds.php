<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\StaleHoldReminderMailable;
use App\Models\LegalHold;
use App\Models\User;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use App\Support\LegalHoldRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-68 STALE-SWEEP-1: a hold active longer than
 * config('legal_hold.stale_after_days') is "stale" — litigation has
 * probably resolved but the hold still blocks retention. This daily
 * sweep emits the legal_hold_stale_count gauge, fires/resolves the
 * legal_hold_stale alert, and nudges each owning landlord (resolved per
 * polymorphic subject) to confirm or release, at most once per
 * config('legal_hold.stale_reminder_cooldown_days') via last_reminded_at.
 */
class SweepStaleHolds extends Command
{
    protected $signature = 'legal-hold:sweep-stale';

    protected $description = 'Emit the stale-hold gauge/alert and remind landlords to review holds active past the stale threshold';

    public function handle(MetricsService $metrics, AlertFiringRecorder $alerts): int
    {
        $now = now();
        $staleThreshold = $now->copy()->subDays((int) config('legal_hold.stale_after_days', 365));
        $cooldownThreshold = $now->copy()->subDays((int) config('legal_hold.stale_reminder_cooldown_days', 30));

        $stale = LegalHold::query()
            ->active()
            ->where('held_at', '<=', $staleThreshold)
            ->whereIn('holdable_type', LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES)
            ->get();

        $metrics->gauge('legal_hold_stale_count', (float) $stale->count());

        if ($stale->isEmpty()) {
            $alerts->resolve('legal_hold_stale');
            $this->info('No stale holds.');

            return self::SUCCESS;
        }

        $alerts->record('legal_hold_stale', (float) $stale->count(), 0.0, ['window' => 'instantaneous']);

        $landlordByHoldId = $this->resolveLandlords($stale);

        // Orphaned stale holds (subject hard-deleted out from under the hold)
        // can never be reminded NOR released through the UI — they would pin
        // the sev3 alert open forever. Surface them as a distinct diagnostic
        // so ops can hand-clean them (docs/runbooks/legal-hold.md#stale-holds).
        $orphans = $stale->filter(fn (LegalHold $hold) => ($landlordByHoldId[$hold->id] ?? null) === null);
        $metrics->gauge('legal_hold_stale_orphan_count', (float) $orphans->count());
        foreach ($orphans as $orphan) {
            Log::warning('legal_hold_stale_orphan', [
                'hold_id' => $orphan->id,
                'holdable_type' => $orphan->holdable_type,
                'holdable_id' => $orphan->holdable_id,
            ]);
        }

        $eligible = $stale->filter(
            fn (LegalHold $hold) => $hold->last_reminded_at === null || $hold->last_reminded_at->lessThanOrEqualTo($cooldownThreshold),
        );

        $remindedHolds = 0;
        foreach ($eligible->groupBy(fn (LegalHold $hold) => $landlordByHoldId[$hold->id] ?? null) as $landlordId => $holds) {
            if ($landlordId === null || $landlordId === '') {
                continue;
            }

            $landlord = User::find((int) $landlordId);
            if ($landlord === null) {
                continue;
            }

            Mail::to($landlord)->queue(new StaleHoldReminderMailable($landlord, $this->summarise($holds, $now)));

            LegalHold::whereIn('id', $holds->pluck('id'))->update(['last_reminded_at' => $now]);
            $remindedHolds += $holds->count();
        }

        $this->info("Stale holds: {$stale->count()}; reminders sent for {$remindedHolds} hold(s).");

        return self::SUCCESS;
    }

    /**
     * Resolve each stale hold's owning landlord_id, grouped by subject type
     * so the lookup costs at most one query per ALLOWED_HOLDABLE_TYPES.
     *
     * @param  Collection<int, LegalHold>  $holds
     * @return array<int, int|null> hold id => landlord id
     */
    private function resolveLandlords(Collection $holds): array
    {
        $map = [];

        foreach ($holds->groupBy('holdable_type') as $type => $group) {
            if (! in_array($type, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true)) {
                continue;
            }

            $owners = $type::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $group->pluck('holdable_id')->all())
                ->pluck('landlord_id', 'id');

            foreach ($group as $hold) {
                $owner = $owners[$hold->holdable_id] ?? null;
                $map[$hold->id] = $owner !== null ? (int) $owner : null;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, LegalHold>  $holds
     * @return array<int, array{type: string, id: int, reason: string, held_at: ?string, days_held: int}>
     */
    private function summarise(Collection $holds, \Illuminate\Support\Carbon $now): array
    {
        return $holds->map(fn (LegalHold $hold) => [
            'type' => class_basename($hold->holdable_type),
            'id' => (int) $hold->holdable_id,
            'reason' => (string) $hold->reason,
            'held_at' => $hold->held_at?->toIso8601String(),
            'days_held' => $hold->held_at !== null ? (int) $hold->held_at->diffInDays($now) : 0,
        ])->values()->all();
    }
}
