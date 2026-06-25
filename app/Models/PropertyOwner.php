<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Enums\ManagementFeeType;
use App\Exceptions\DataIntegrityException;
use App\Services\ManagementFee\ManagementFeeCalculator;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase-101 OWNER-FOUNDATION: a property owner — the party a property manager (the
 * landlord) manages properties on behalf of. A landlord-scoped CONTACT (name/email),
 * not a login user; user_id is reserved for a later owner-portal phase.
 */
class PropertyOwner extends Model
{
    use Auditable, HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'user_id',
        'name',
        'email',
        'phone',
        'id_number',
        'notes',
        'is_active',
        'management_fee_type',
        'management_fee_value',
        'management_fee_base',
        'management_fee_flat_cadence',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'management_fee_value' => 'decimal:2',
            'management_fee_type' => ManagementFeeType::class,
            'management_fee_base' => ManagementFeeBase::class,
            'management_fee_flat_cadence' => ManagementFeeFlatCadence::class,
            'management_fee_locked_at' => 'datetime',
        ];
    }

    /**
     * Slice-2 PR-2.3 drift-lock: once an active agreement governs the fee,
     * management_fee_* is immutable except through AgreementApplicator (an
     * amendment that is re-signed). The applicator writes via withoutFeeLock();
     * every other save path that touches a fee column while locked is refused
     * fail-closed, so a stray Owners-UI edit, import, or tinker can't drift the
     * owner's net away from what the signed contract says.
     */
    private static bool $feeLockBypassed = false;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutFeeLock(callable $callback): mixed
    {
        $previous = self::$feeLockBypassed;
        self::$feeLockBypassed = true;

        try {
            return $callback();
        } finally {
            self::$feeLockBypassed = $previous;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (PropertyOwner $owner): void {
            if (self::$feeLockBypassed || $owner->management_fee_locked_at === null) {
                return;
            }

            $feeColumns = [
                'management_fee_type', 'management_fee_value', 'management_fee_base',
                'management_fee_flat_cadence', 'management_fee_locked_at', 'management_agreement_id',
            ];

            foreach ($feeColumns as $column) {
                if ($owner->isDirty($column)) {
                    throw new DataIntegrityException(
                        'The management fee is locked by a signed agreement; amend the agreement to change it.',
                        'owner.fee_locked',
                    );
                }
            }
        });
    }

    public function isFeeLocked(): bool
    {
        return $this->management_fee_locked_at !== null;
    }

    /** Slice-2 PR-2.3: the agreement that wrote + locked this owner's fee (null until activated). */
    public function managingAgreement(): BelongsTo
    {
        return $this->belongsTo(ManagementAgreement::class, 'management_agreement_id');
    }

    /**
     * Phase-103 OWNER-PAYOUTS: the PM's management fee on a period's collected (gross)
     * rent — the standard property-management model (% of rent collected, or a flat
     * amount per statement). 'none' (the default) yields 0, so the owner's net is
     * unchanged from before fees existed.
     *
     * Collected-only shortcut. For the full model (billed/scheduled bases, per-unit
     * flat), use {@see \App\Services\ManagementFee\ManagementFeeCalculator}; this stays
     * for callers that only have a collected figure and a default-base relationship.
     */
    public function managementFeeOn(float $collected): float
    {
        return round(match ($this->management_fee_type) {
            // Clamp the rate to [0, 100] here (not only in the FormRequest) so a value set by a
            // seeder/import/tinker can never drive the owner's net negative (top OR bottom).
            ManagementFeeType::Percentage => $collected * max(0.0, min((float) $this->management_fee_value, ManagementFeeCalculator::MAX_PERCENTAGE)) / 100,
            ManagementFeeType::Flat => (float) $this->management_fee_value,
            ManagementFeeType::None => 0.0,
        }, 2);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** Phase-102 OWNER-PORTAL: the login linked to this owner (null until invited+accepted). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The properties this owner holds (managed by the landlord). */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'property_owner_id');
    }

    /** Phase-103 OWNER-PAYOUTS: disbursements the landlord has remitted to this owner. */
    public function payouts(): HasMany
    {
        return $this->hasMany(OwnerPayout::class, 'property_owner_id');
    }

    /**
     * @param  Builder<PropertyOwner>  $query
     * @return Builder<PropertyOwner>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
