<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeterStatus;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Phase-86 WATER-METER-FOUNDATION: a physical water meter. Readings hang off a
 * meter (not a unit), so a meter can be replaced without breaking consumption
 * continuity and a unit/line can carry sub-meters. utility_type is 'water' for
 * now but the model is built to extend to other utilities later.
 */
class Meter extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    protected $table = 'water_meters';

    protected $fillable = [
        'landlord_id',
        'building_id',
        'unit_id',
        'parent_meter_id',
        'serial_number',
        'utility_type',
        'meter_type',
        'status',
        'initial_reading',
        'installed_at',
        'decommissioned_at',
        'disconnected_at',
        'disconnect_reason',
        'replaced_by_meter_id',
        'notes',
    ];

    protected $casts = [
        'status' => MeterStatus::class,
        'initial_reading' => 'decimal:2',
        'installed_at' => 'date',
        'decommissioned_at' => 'date',
        'disconnected_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function parentMeter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_meter_id');
    }

    public function subMeters(): HasMany
    {
        return $this->hasMany(self::class, 'parent_meter_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_meter_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(WaterReading::class);
    }

    /**
     * The most recent reading, for eager-loading the current value without an
     * N+1 over a meter fleet (review LOW).
     */
    public function latestReading(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WaterReading::class)->latestOfMany(['reading_date' => 'max', 'id' => 'max']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Meter>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Meter>
     */
    public function scopeActive($query)
    {
        return $query->where('status', MeterStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === MeterStatus::Active;
    }

    /**
     * Phase-90: water service is cut (distinct from device lifecycle status).
     */
    public function isDisconnected(): bool
    {
        return $this->disconnected_at !== null;
    }

    /**
     * Phase-90 THE CAVEAT: only a unit's own meter may be disconnected — never a
     * shared/main meter (it feeds sub-meters and would cut the whole building).
     */
    public function isUnitMeter(): bool
    {
        return $this->unit_id !== null
            && $this->parent_meter_id === null
            && $this->subMeters()->count() === 0;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Meter>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Meter>
     */
    public function scopeDisconnected($query)
    {
        return $query->whereNotNull('disconnected_at');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Meter>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Meter>
     */
    public function scopeConnected($query)
    {
        return $query->whereNull('disconnected_at');
    }

    /**
     * The reading value the next consumption is measured from: the latest
     * reading's current value, or the meter's (possibly non-zero) baseline when
     * no reading exists yet.
     */
    public function baselineForNextReading(): float
    {
        $latest = $this->readings()
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->first();

        return (float) ($latest->current_reading ?? $this->initial_reading);
    }

    /**
     * Resolve the active meter for a unit, lazily creating one the first time a
     * reading is recorded for a unit that pre-dates the meter model. Keeps the
     * existing "just enter a reading" flow working while making meters canonical.
     */
    public static function resolveActiveForUnit(Unit $unit): self
    {
        // Review H2: serialize on the unit row so two concurrent first-readings
        // for the same unit cannot each create an active meter (orphaned
        // readings that the biller would never see).
        return DB::transaction(function () use ($unit) {
            Unit::query()->whereKey($unit->id)->lockForUpdate()->first();

            $meter = static::query()
                ->where('unit_id', $unit->id)
                ->active()
                ->orderByDesc('id')
                ->first();

            if ($meter) {
                return $meter;
            }

            return static::create([
                'landlord_id' => $unit->landlord_id,
                'building_id' => $unit->building_id,
                'unit_id' => $unit->id,
                'serial_number' => $unit->meter_number,
                'utility_type' => 'water',
                'status' => MeterStatus::Active->value,
                'initial_reading' => 0,
                'installed_at' => now()->toDateString(),
            ]);
        });
    }
}
