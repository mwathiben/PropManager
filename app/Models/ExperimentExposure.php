<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-35 PLATFORM-EXP-1: append-only. Never UPDATE — sticky
 * exposure means once a (user, experiment) row exists the variant
 * is frozen. The migration's unique constraint enforces this at
 * the DB level.
 */
class ExperimentExposure extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'experiment_key',
        'variant_key',
        'fired_at',
    ];

    protected $casts = [
        'fired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
