<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSchedule extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'name',
        'type',
        'trigger',
        'days_offset',
        'send_time',
        'channels',
        'template_id',
        'is_active',
        'last_run_at',
    ];

    protected $casts = [
        'channels' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'days_offset' => 'integer',
    ];

    /**
     * Get the landlord who owns this schedule
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the template for this schedule
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * Scope to get active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get schedules of a specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get schedules with a specific trigger
     */
    public function scopeWithTrigger($query, string $trigger)
    {
        return $query->where('trigger', $trigger);
    }

    /**
     * Check if this schedule should run now
     */
    public function shouldRunNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $currentTime = now()->format('H:i');
        $scheduleTime = $this->send_time;

        // Check if within 5-minute window
        $scheduleMins = $this->timeToMinutes($scheduleTime);
        $currentMins = $this->timeToMinutes($currentTime);

        return abs($scheduleMins - $currentMins) <= 5;
    }

    /**
     * Convert time string to minutes since midnight
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 60) + (int) $parts[1];
    }

    /**
     * Get the next scheduled run time
     */
    public function getNextRunAttribute(): ?string
    {
        if (! $this->is_active) {
            return null;
        }

        $today = now()->format('Y-m-d');
        $scheduleTime = $this->send_time;
        $nextRun = \Carbon\Carbon::parse("{$today} {$scheduleTime}");

        if ($nextRun->isPast()) {
            $nextRun->addDay();
        }

        return $nextRun->format('M d, Y \a\t H:i');
    }

    /**
     * Mark this schedule as run
     */
    public function markAsRun(): void
    {
        $this->update(['last_run_at' => now()]);
    }

    /**
     * Get human-readable trigger description
     */
    public function getTriggerDescriptionAttribute(): string
    {
        return match ($this->trigger) {
            'days_before_due' => "{$this->days_offset} days before rent is due",
            'days_after_overdue' => "{$this->days_offset} days after rent is overdue",
            'days_before_expiry' => "{$this->days_offset} days before lease expires",
            default => $this->trigger,
        };
    }

    /**
     * Get available triggers for a schedule type
     */
    public static function getAvailableTriggers(string $type): array
    {
        return match ($type) {
            'rent_reminder' => [
                'days_before_due' => 'Days before rent is due',
            ],
            'arrears_notice' => [
                'days_after_overdue' => 'Days after rent is overdue',
            ],
            'lease_expiry' => [
                'days_before_expiry' => 'Days before lease expires',
            ],
            default => [],
        };
    }
}
