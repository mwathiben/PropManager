<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-66 NPS-SURVEY-1: a single Net Promoter Score response.
 *
 * `category` is always derived from `score` via categorise() — never
 * trust a client-supplied bucket.
 */
class NpsResponse extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TenantScope;

    public const CATEGORY_DETRACTOR = 'detractor';

    public const CATEGORY_PASSIVE = 'passive';

    public const CATEGORY_PROMOTER = 'promoter';

    // landlord_id is intentionally NOT fillable — TenantScope's creating
    // hook is the single authority that stamps ownership, so it can never
    // be spoofed via mass assignment.
    protected $fillable = [
        'user_id',
        'score',
        'category',
        'comment',
        'context',
        'prompted_at',
        'responded_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'prompted_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    /**
     * Map a 0-10 score to its NPS bucket. 0-6 detractor, 7-8 passive,
     * 9-10 promoter — the canonical Net Promoter Score thresholds.
     */
    public static function categorise(int $score): string
    {
        return match (true) {
            $score <= 6 => self::CATEGORY_DETRACTOR,
            $score <= 8 => self::CATEGORY_PASSIVE,
            default => self::CATEGORY_PROMOTER,
        };
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
