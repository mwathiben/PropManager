<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-56 MULTI-TOUCH-1: a single touch in a user's attribution journey.
 *
 * Many touchpoints precede a single conversion — AttributionModelService
 * allocates conversion credit across the touch sequence per the chosen
 * model (first / last / linear / u-shape).
 */
class AttributionTouchpoint extends Model
{
    use HasFactory;

    public const CHANNEL_REFERRAL = 'referral';

    public const CHANNEL_ORGANIC_SEARCH = 'organic_search';

    public const CHANNEL_PAID_SEARCH = 'paid_search';

    public const CHANNEL_SOCIAL = 'social';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_DIRECT = 'direct';

    public const CHANNEL_INVITATION = 'invitation';

    protected $fillable = [
        'user_id',
        'channel',
        'medium',
        'campaign',
        'landlord_id',
        'touched_at',
    ];

    protected $casts = [
        'touched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
