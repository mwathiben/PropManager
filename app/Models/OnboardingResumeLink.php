<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-46 PROGRESS-RESUME-1: audit row for each signed onboarding-
 * resume URL issued or consumed.
 *
 * @property int $id
 * @property int $onboarding_session_id
 * @property string $signature_hash
 * @property \Carbon\Carbon $signed_until
 * @property \Carbon\Carbon $generated_at
 * @property int|null $generated_by_user_id
 * @property \Carbon\Carbon|null $consumed_at
 * @property string|null $consumed_from_ip
 */
class OnboardingResumeLink extends Model
{
    protected $fillable = [
        'onboarding_session_id',
        'signature_hash',
        'signed_until',
        'generated_at',
        'generated_by_user_id',
        'consumed_at',
        'consumed_from_ip',
    ];

    protected $casts = [
        'signed_until' => 'datetime',
        'generated_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OnboardingSession::class, 'onboarding_session_id');
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
