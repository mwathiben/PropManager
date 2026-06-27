<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\StaleHoldReminderMailable;
use App\Models\LandlordHoldSettings;
use App\Models\LegalHold;
use App\Models\User;
use App\Services\Legal\HoldSettingsResolver;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use App\Support\LegalHoldRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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

    public function handle(MetricsService $metrics, AlertFiringRecorder $alerts, HoldSettingsResolver $settings): int
    {
        $now = now();
        $globalStale = (int) config('legal_hold.stale_after_days', 365);

        $candidates = $this->fetchCandidates($now, $globalStale);
        $landlordByHoldId = $this->resolveLandlords($candidates);

        /** @var array{globalStale: int, now: Carbon} $ctx */
        $ctx = ['globalStale' => $globalStale, 'now' => $now];
        $stale = $this->filterStaleHolds($candidates, $landlordByHoldId, $settings, $ctx);

        $metrics->gauge('legal_hold_stale_count', (float) $stale->count());

        if ($stale->isEmpty()) {
            $alerts->resolve('legal_hold_stale');
            $this->info('No stale holds.');

            return self::SUCCESS;
        }

        $alerts->record('legal_hold_stale', (float) $stale->count(), 0.0, ['window' => 'instantaneous']);

        $orphans = $stale->filter(fn (LegalHold $hold) => ($landlordByHoldId[$hold->id] ?? null) === null);
        $this->processOrphans($orphans, $metrics);

        $remindedHolds = $this->sendReminders($stale, $landlordByHoldId, $settings, $now);

        $this->info("Stale holds: {$stale->count()}; reminders sent for {$remindedHolds} hold(s).");

        return self::SUCCESS;
    }

    /**
     * Compute the minimum stale window across all landlord overrides and the
     * global default so the DB query pre-filters as tightly as possible.
     */
    private function computeMinStaleWindow(int $globalStale): int
    {
        $minOverride = (int) (LandlordHoldSettings::query()
            ->whereNotNull('stale_after_days')
            ->min('stale_after_days') ?? $globalStale);

        return (int) min($globalStale, $minOverride);
    }

    /**
     * Fetch candidate holds from the DB pre-filtered by the shortest stale
     * window so no landlord's holds are missed.
     */
    private function fetchCandidates(Carbon $now, int $globalStale): Collection
    {
        $minWindow = $this->computeMinStaleWindow($globalStale);

        return LegalHold::query()
            ->active()
            ->where('held_at', '<=', $now->copy()->subDays($minWindow))
            ->whereIn('holdable_type', LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES)
            ->get();
    }

    /**
     * Keep only holds past THEIR landlord's effective stale window; orphans
     * (no resolvable landlord) fall back to the global default.
     *
     * @param  array{globalStale: int, now: Carbon}  $ctx
     */
    private function filterStaleHolds(
        Collection $candidates,
        array $landlordByHoldId,
        HoldSettingsResolver $settings,
        array $ctx,
    ): Collection {
        $globalStale = $ctx['globalStale'];
        $now = $ctx['now'];

        return $candidates->filter(function (LegalHold $hold) use ($landlordByHoldId, $settings, $globalStale, $now) {
            $landlordId = $landlordByHoldId[$hold->id] ?? null;
            $window = $landlordId !== null ? $settings->staleAfterDays($landlordId) : $globalStale;

            return $hold->held_at !== null && $hold->held_at->lessThanOrEqualTo($now->copy()->subDays($window));
        })->values();
    }

    /**
     * Emit the orphan gauge and log each orphan for ops to hand-clean
     * (docs/runbooks/legal-hold.md#stale-holds).
     */
    private function processOrphans(Collection $orphans, MetricsService $metrics): void
    {
        $metrics->gauge('legal_hold_stale_orphan_count', (float) $orphans->count());

        foreach ($orphans as $orphan) {
            Log::warning('legal_hold_stale_orphan', [
                'hold_id' => $orphan->id,
                'holdable_type' => $orphan->holdable_type,
                'holdable_id' => $orphan->holdable_id,
            ]);
        }
    }

    /**
     * Send cooldown-gated reminders for all stale holds grouped by landlord.
     * Returns the total number of holds for which reminders were sent.
     */
    private function sendReminders(
        Collection $stale,
        array $landlordByHoldId,
        HoldSettingsResolver $settings,
        Carbon $now,
    ): int {
        $remindedHolds = 0;

        foreach ($stale->groupBy(fn (LegalHold $hold) => $landlordByHoldId[$hold->id] ?? null) as $landlordId => $holds) {
            if ($landlordId === null || $landlordId === '') {
                continue;
            }

            $landlord = User::find((int) $landlordId);
            if ($landlord === null) {
                continue;
            }

            /** @var array{settings: HoldSettingsResolver, now: Carbon, landlordId: int} $rctx */
            $rctx = ['settings' => $settings, 'now' => $now, 'landlordId' => (int) $landlordId];
            $remindedHolds += $this->sendRemindersForLandlord($holds, $landlord, $rctx);
        }

        return $remindedHolds;
    }

    /**
     * Apply the per-landlord cooldown, queue mails for eligible holds, and
     * stamp last_reminded_at. Returns the count of reminded holds.
     *
     * @param  array{settings: HoldSettingsResolver, now: Carbon, landlordId: int}  $ctx
     */
    private function sendRemindersForLandlord(Collection $holds, User $landlord, array $ctx): int
    {
        $settings = $ctx['settings'];
        $now = $ctx['now'];
        $landlordId = $ctx['landlordId'];

        $cooldownThreshold = $now->copy()->subDays($settings->reminderCooldownDays($landlordId));
        $eligible = $holds->filter(
            fn (LegalHold $hold) => $hold->last_reminded_at === null || $hold->last_reminded_at->lessThanOrEqualTo($cooldownThreshold),
        );

        if ($eligible->isEmpty()) {
            return 0;
        }

        $summary = $this->summarise($eligible, $now);
        $recipients = $settings->effective($landlordId)['reminder_recipients'];

        if ($recipients !== []) {
            foreach ($recipients as $email) {
                Mail::to($email)->queue(new StaleHoldReminderMailable($landlord, $summary));
            }
        } else {
            Mail::to($landlord)->queue(new StaleHoldReminderMailable($landlord, $summary));
        }

        LegalHold::whereIn('id', $eligible->pluck('id'))->update(['last_reminded_at' => $now]);

        return $eligible->count();
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
    private function summarise(Collection $holds, \Carbon\CarbonInterface $now): array
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
