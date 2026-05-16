<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experiment extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CONCLUDED = 'concluded';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RUNNING,
        self::STATUS_PAUSED,
        self::STATUS_CONCLUDED,
    ];

    public const CONTROL_VARIANT = 'control';

    protected $fillable = [
        'experiment_key',
        'name',
        'status',
        'variants',
        'winning_variant_key',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'variants' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_RUNNING) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
