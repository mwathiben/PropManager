<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'feature',
        'quantity',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function currentPeriod(): array
    {
        return [
            'start' => now()->startOfMonth(),
            'end' => now()->endOfMonth(),
        ];
    }

    public static function forUserAndFeature(int $userId, string $feature): ?self
    {
        $period = self::currentPeriod();

        return self::where('user_id', $userId)
            ->where('feature', $feature)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();
    }

    public static function incrementUsage(int $userId, string $feature, int $amount = 1): self
    {
        $period = self::currentPeriod();

        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'feature' => $feature,
                'period_start' => $period['start'],
            ],
            [
                'quantity' => \DB::raw("quantity + {$amount}"),
                'period_end' => $period['end'],
            ]
        );
    }

    public static function setUsage(int $userId, string $feature, int $quantity): self
    {
        $period = self::currentPeriod();

        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'feature' => $feature,
                'period_start' => $period['start'],
            ],
            [
                'quantity' => $quantity,
                'period_end' => $period['end'],
            ]
        );
    }
}
