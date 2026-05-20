<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-66 ONBOARDING-TOUR-1: per-user progress through a named in-app
 * tour. Terminal once completed/dismissed — the {@see \App\Services\Onboarding\TourService}
 * never surfaces a tour again after that, so the server (not the client)
 * is the source of truth for whether a tour shows.
 *
 * @property int $id
 * @property int $user_id
 * @property string $tour_key
 * @property int $current_step
 * @property string $status
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $last_advanced_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $dismissed_at
 */
class UserTourState extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'user_id',
        'tour_key',
        'current_step',
        'status',
        'started_at',
        'last_advanced_at',
        'completed_at',
        'dismissed_at',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'started_at' => 'datetime',
        'last_advanced_at' => 'datetime',
        'completed_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A terminal tour (completed or dismissed) never re-triggers.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_DISMISSED], true);
    }
}
