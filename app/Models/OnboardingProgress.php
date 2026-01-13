<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingProgress extends Model
{
    protected $table = 'onboarding_progress';

    protected $fillable = [
        'user_id',
        'current_step',
        'total_steps',
        'step_data',
        'completed_steps',
        'is_complete',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'step_data' => 'array',
        'completed_steps' => 'array',
        'is_complete' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_step' => 'integer',
        'total_steps' => 'integer',
    ];

    /**
     * Step names for reference
     */
    const STEPS = [
        1 => 'welcome',
        2 => 'profile',
        3 => 'property',
        4 => 'structure',
        5 => 'financial',
        6 => 'team',
        7 => 'first_tenant',
        8 => 'complete',
    ];

    /**
     * Optional steps that can be skipped
     */
    const OPTIONAL_STEPS = [6, 7]; // team, first_tenant

    /**
     * Get the user this progress belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the current step name
     */
    public function getCurrentStepNameAttribute(): string
    {
        return self::STEPS[$this->current_step] ?? 'unknown';
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->is_complete) {
            return 100;
        }

        $completedCount = count($this->completed_steps ?? []);

        return (int) round(($completedCount / $this->total_steps) * 100);
    }

    /**
     * Check if a step is completed
     */
    public function isStepCompleted(int $step): bool
    {
        return in_array($step, $this->completed_steps ?? []);
    }

    /**
     * Check if a step is optional
     */
    public static function isStepOptional(int $step): bool
    {
        return in_array($step, self::OPTIONAL_STEPS);
    }

    /**
     * Mark a step as completed
     */
    public function completeStep(int $step): void
    {
        $completedSteps = $this->completed_steps ?? [];
        if (! in_array($step, $completedSteps)) {
            $completedSteps[] = $step;
            sort($completedSteps);
            $this->completed_steps = $completedSteps;
        }

        // Move to next step if not at the end
        if ($step < $this->total_steps) {
            $this->current_step = $step + 1;
        }

        $this->save();
    }

    /**
     * Skip a step (only for optional steps)
     */
    public function skipStep(int $step): bool
    {
        if (! self::isStepOptional($step)) {
            return false;
        }

        $this->completeStep($step);

        return true;
    }

    /**
     * Save step data
     */
    public function saveStepData(int $step, array $data): void
    {
        $stepData = $this->step_data ?? [];
        $stepData[$step] = $data;
        $this->step_data = $stepData;
        $this->save();
    }

    /**
     * Get data for a specific step
     */
    public function getStepData(int $step): array
    {
        return $this->step_data[$step] ?? [];
    }

    /**
     * Mark onboarding as complete
     */
    public function markComplete(): void
    {
        $this->is_complete = true;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Reset onboarding progress
     */
    public function reset(): void
    {
        $this->current_step = 1;
        $this->step_data = [];
        $this->completed_steps = [];
        $this->is_complete = false;
        $this->completed_at = null;
        $this->save();
    }

    /**
     * Start onboarding (set started_at if not already set)
     */
    public function start(): void
    {
        if (! $this->started_at) {
            $this->started_at = now();
            $this->save();
        }
    }

    /**
     * Create or get onboarding progress for a user
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'current_step' => 1,
                'total_steps' => 8,
                'completed_steps' => [],
                'step_data' => [],
                'is_complete' => false,
            ]
        );
    }
}
