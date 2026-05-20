<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\NpsPromptState;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase-66 NPS-SURVEY-2: server-authoritative NPS prompt gating.
 *
 * The server decides whether to prompt; the client only renders what
 * it is told. Every suppression/cadence rule lives here so a tampered
 * client can neither spam nor silence the survey:
 *  - config('nps.enabled') is a GLOBAL kill-switch (evaluated first).
 *  - super-admins (ops users) are never surveyed.
 *  - accounts younger than min_account_age_days are too new to judge.
 *  - one response per cadence_days window.
 *  - dismiss snoozes for snooze_days; max_dismissals stops asking.
 *  - opt-out is terminal.
 *  - a recently-shown prompt is suppressed for reprompt_cooldown_days
 *    so navigating between pages doesn't re-nag within a session.
 *
 * Quiet hours are deliberately NOT consulted: an in-app modal shown to
 * a user who is actively rendering a page is not a night-time push, so
 * the quiet-hours notification primitive doesn't apply here.
 */
class NpsEligibilityService
{
    public function shouldPrompt(User $user): bool
    {
        if (! (bool) config('nps.enabled', true)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return false;
        }

        $minAge = (int) config('nps.min_account_age_days', 14);
        if ($user->created_at === null || $user->created_at->greaterThan(now()->subDays($minAge))) {
            return false;
        }

        $state = $this->stateFor($user);

        if ($state->opted_out_at !== null) {
            return false;
        }

        if ($state->snoozed_until !== null && $state->snoozed_until->isFuture()) {
            return false;
        }

        if ($state->dismiss_count >= (int) config('nps.max_dismissals', 3)) {
            return false;
        }

        $cadence = (int) config('nps.cadence_days', 90);
        if ($state->last_responded_at !== null && $state->last_responded_at->greaterThan(now()->subDays($cadence))) {
            return false;
        }

        $cooldown = (int) config('nps.reprompt_cooldown_days', 1);
        if ($state->last_prompted_at !== null && $state->last_prompted_at->greaterThan(now()->subDays($cooldown))) {
            return false;
        }

        return true;
    }

    /**
     * The Inertia-shared payload, or null when ineligible. Kept minimal
     * — the client only needs to know it may show the modal + in which
     * context. Cached 60s (busted on every state mutation).
     *
     * @return array{context: string}|null
     */
    public function promptPayloadFor(User $user): ?array
    {
        return Cache::remember('nps:prompt:'.$user->id, 60, function () use ($user) {
            if (! $this->shouldPrompt($user)) {
                return null;
            }

            $contexts = (array) config('nps.contexts', ['dashboard']);

            return ['context' => (string) ($contexts[0] ?? 'dashboard')];
        });
    }

    /**
     * Server-side double-submit guard: refuse a second response inside
     * the active cadence window even if the client re-POSTs.
     */
    public function hasRespondedRecently(User $user): bool
    {
        $state = NpsPromptState::where('user_id', $user->id)->first();

        if ($state === null || $state->last_responded_at === null) {
            return false;
        }

        $cadence = (int) config('nps.cadence_days', 90);

        return $state->last_responded_at->greaterThan(now()->subDays($cadence));
    }

    public function markPrompted(User $user): void
    {
        $this->stateFor($user)->forceFill(['last_prompted_at' => now()])->save();
        $this->forgetCache($user);
    }

    public function markResponded(User $user): void
    {
        $this->stateFor($user)->forceFill(['last_responded_at' => now()])->save();
        $this->forgetCache($user);
    }

    public function markDismissed(User $user): void
    {
        $state = $this->stateFor($user);
        $max = (int) config('nps.max_dismissals', 3);

        // Atomic increment (clamped at max) so concurrent dismisses can't
        // lose an update or grow the counter unboundedly under spam.
        $state->newQuery()
            ->whereKey($state->getKey())
            ->update([
                'dismiss_count' => DB::raw('LEAST(dismiss_count + 1, '.$max.')'),
                'snoozed_until' => now()->addDays((int) config('nps.snooze_days', 30)),
            ]);

        $this->forgetCache($user);
    }

    public function optOut(User $user): void
    {
        $this->stateFor($user)->forceFill(['opted_out_at' => now()])->save();
        $this->forgetCache($user);
    }

    private function stateFor(User $user): NpsPromptState
    {
        return NpsPromptState::firstOrCreate(['user_id' => $user->id]);
    }

    private function forgetCache(User $user): void
    {
        Cache::forget('nps:prompt:'.$user->id);
    }
}
