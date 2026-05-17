<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-48 CARETAKER-ASSIGNMENT-UX-1: audit trail for caretaker building
 * assignments. buildings.caretaker_id stays as the canonical "currently
 * assigned" link; this table captures the workflow.
 *
 * @property int $id
 * @property int $caretaker_id
 * @property int $building_id
 * @property string $status  pending|accepted|declined
 * @property \Carbon\Carbon $assigned_at
 * @property \Carbon\Carbon|null $decided_at
 * @property string|null $decision_reason
 */
class CaretakerAssignment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'caretaker_id',
        'building_id',
        'status',
        'assigned_at',
        'decided_at',
        'decision_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function caretaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caretaker_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
