<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-46 WIZARD-INFRA-1: per-user onboarding wizard state. Narrow
 * shape (current_step + history + lifecycle timestamps) — real form
 * data lives in canonical models, not in a step_data JSON blob.
 *
 * @property int $id
 * @property int $user_id
 * @property string $role
 * @property int $current_step
 * @property list<array{step: int, action: string, at: string}> $step_history
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon $last_touched_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $abandoned_at
 * @property \Carbon\Carbon|null $last_nudge_sent_at
 */
class OnboardingSession extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'current_step',
        'step_history',
        'started_at',
        'last_touched_at',
        'completed_at',
        'abandoned_at',
        'last_nudge_sent_at',
    ];

    protected $casts = [
        'step_history' => 'array',
        'started_at' => 'datetime',
        'last_touched_at' => 'datetime',
        'completed_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'last_nudge_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Phase-46 WIZARD-INFRA-1: find-or-create the live session for a user.
     * "Live" = not completed AND not abandoned. Auto-seals 30-day-stale
     * rows into abandoned_at before checking.
     */
    public static function firstFor(User $user): self
    {
        $existing = static::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->whereNull('abandoned_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::create([
            'user_id' => $user->id,
            'role' => $user->role,
            'current_step' => 1,
            'step_history' => [],
            'started_at' => now(),
            'last_touched_at' => now(),
        ]);
    }

    public function isActive(): bool
    {
        return $this->completed_at === null && $this->abandoned_at === null;
    }
}
