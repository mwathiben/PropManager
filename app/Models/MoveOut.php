<?php

namespace App\Models;

use App\Enums\MoveOutStatus;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoveOut extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'lease_id',
        'notice_date',
        'intended_move_out_date',
        'actual_move_out_date',
        'status',
        'inspection_notes',
        'deposit_held',
        'total_deductions',
        'arrears_balance',
        'refund_amount',
        'settlement_method',
        'settlement_reference',
        'settled_at',
        'processed_by',
    ];

    protected $casts = [
        'status' => MoveOutStatus::class,
        'notice_date' => 'date',
        'intended_move_out_date' => 'date',
        'actual_move_out_date' => 'date',
        'deposit_held' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'arrears_balance' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    /**
     * Phase-29 WF-VACANCY-2: when MoveOut.status transitions TO
     * Completed and the unit has no future-dated active lease, fire
     * VacancyDetected so the listener can create the list-unit task.
     */
    protected static function booted(): void
    {
        static::updated(function (self $moveOut) {
            if (! $moveOut->wasChanged('status')) {
                return;
            }
            if ($moveOut->status !== MoveOutStatus::Completed) {
                return;
            }
            $unit = $moveOut->lease?->unit;
            if (! $unit) {
                return;
            }
            $hasFutureLease = Lease::query()
                ->withoutGlobalScope('landlord')
                ->where('unit_id', $unit->id)
                ->where('start_date', '>', now()->toDateString())
                ->exists();
            if ($hasFutureLease) {
                return;
            }
            \App\Events\VacancyDetected::dispatch(
                $unit,
                $moveOut,
                \Carbon\CarbonImmutable::now(),
            );
        });
    }

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the lease
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Get the user who processed this move-out
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the tenant through the lease
     */
    public function tenant()
    {
        return $this->hasOneThrough(User::class, Lease::class, 'id', 'id', 'lease_id', 'tenant_id');
    }

    /**
     * Get all deductions
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(MoveOutDeduction::class);
    }

    /**
     * Get all inspection results
     */
    public function inspectionResults(): HasMany
    {
        return $this->hasMany(MoveOutInspectionResult::class);
    }

    /**
     * Calculate the refund amount
     */
    public function calculateRefund(): float
    {
        $totalDeductions = $this->deductions()->sum('amount');
        $this->total_deductions = $totalDeductions;
        $this->refund_amount = max(0, $this->deposit_held - $totalDeductions - $this->arrears_balance);

        return $this->refund_amount;
    }

    /**
     * Check if move-out is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === MoveOutStatus::Completed;
    }

    /**
     * Check if move-out is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === MoveOutStatus::Cancelled;
    }

    /**
     * Check if inspection is complete
     */
    public function isInspectionComplete(): bool
    {
        return in_array($this->status, [MoveOutStatus::InspectionComplete, MoveOutStatus::SettlementPending, MoveOutStatus::Completed]);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, MoveOutStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Active move-outs (not completed or cancelled)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [MoveOutStatus::Completed, MoveOutStatus::Cancelled]);
    }

    /**
     * Scope: Completed move-outs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', MoveOutStatus::Completed);
    }
}
