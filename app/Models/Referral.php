<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ATTRIBUTED = 'attributed';
    public const STATUS_REWARDED = 'rewarded';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ATTRIBUTED,
        self::STATUS_REWARDED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code',
        'status',
        'attributed_at',
        'rewarded_at',
    ];

    protected $casts = [
        'attributed_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
