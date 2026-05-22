<?php

namespace App\Models;

use App\Enums\WaterReadingStatus;
use App\Models\Concerns\RowVersion;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WaterReading extends Model
{
    use Auditable, HasFactory, TenantScope;
    use RowVersion;

    protected $fillable = [
        'version',
        'unit_id',
        'meter_id',
        'landlord_id',
        'reading_date',
        'previous_reading',
        'current_reading',
        'consumption',
        'cost',
        'is_invoiced',
        'photo_path',
        'status',
        'recorded_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'ocr_reading',
        'ocr_verified',
        'is_anomalous',
        'auto_approved',
    ];

    protected $casts = [
        'status' => WaterReadingStatus::class,
        'reading_date' => 'date',
        'reviewed_at' => 'datetime',
        'is_invoiced' => 'boolean',
        'ocr_verified' => 'boolean',
        'is_anomalous' => 'boolean',
        'auto_approved' => 'boolean',
        'previous_reading' => 'decimal:2',
        'current_reading' => 'decimal:2',
        'consumption' => 'decimal:2',
        'cost' => 'decimal:2',
        'ocr_reading' => 'decimal:2',
    ];

    protected $appends = ['photo_url'];

    /**
     * Get the unit this reading belongs to
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The physical meter this reading was taken from (Phase-86). Nullable for
     * legacy rows that pre-date the meter model and were not backfilled.
     */
    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Get the user who recorded this reading
     */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who reviewed this reading
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the photo URL
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo_path && Storage::tenant()->exists($this->photo_path)) {
            return route('readings.photo', $this->id);
        }

        return null;
    }

    /**
     * Check if reading is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === WaterReadingStatus::Pending;
    }

    /**
     * Check if reading is approved
     */
    public function isApproved(): bool
    {
        return $this->status === WaterReadingStatus::Approved;
    }

    /**
     * Check if reading is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === WaterReadingStatus::Rejected;
    }

    /**
     * Approve the reading
     */
    public function approve(int $reviewerId, ?string $notes = null): void
    {
        $this->update([
            'status' => WaterReadingStatus::Approved,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Phase-88: system auto-approval when the landlord review window closes with
     * the reading still pending. Marks it billable (so water revenue is never
     * silently lost) and flags it as a system approval (reviewed_by null).
     */
    public function autoApprove(): void
    {
        $this->update([
            'status' => WaterReadingStatus::Approved,
            'auto_approved' => true,
            'reviewed_by' => null,
            'reviewed_at' => now(),
            'review_notes' => 'Auto-approved after the review window closed.',
        ]);
    }

    /**
     * Reject the reading
     */
    public function reject(int $reviewerId, string $reason): void
    {
        $this->update([
            'status' => WaterReadingStatus::Rejected,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
    }

    /**
     * Check if photo exists
     */
    public function hasPhoto(): bool
    {
        return ! is_null($this->photo_path) && Storage::tenant()->exists($this->photo_path);
    }

    /**
     * Delete the photo file
     */
    public function deletePhoto(): bool
    {
        if ($this->hasPhoto()) {
            return Storage::tenant()->delete($this->photo_path);
        }

        return false;
    }

    /**
     * Scope: Only pending readings
     */
    public function scopePending($query)
    {
        return $query->where('status', WaterReadingStatus::Pending);
    }

    /**
     * Scope: Only approved readings
     */
    public function scopeApproved($query)
    {
        return $query->where('status', WaterReadingStatus::Approved);
    }

    /**
     * Scope: Only rejected readings
     */
    public function scopeRejected($query)
    {
        return $query->where('status', WaterReadingStatus::Rejected);
    }

    /**
     * Scope: Readings ready for invoicing (approved but not yet invoiced)
     */
    public function scopeReadyForInvoicing($query)
    {
        return $query->where('status', WaterReadingStatus::Approved)
            ->where('is_invoiced', false);
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When deleting a reading, also delete the photo
        static::deleting(function ($reading) {
            $reading->deletePhoto();
        });
    }
}
