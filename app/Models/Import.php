<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'imported_by',
        'type',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'errors',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => ImportStatus::class,
        'errors' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who imported this data
     */
    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * Get the landlord who owns this import
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Check if import is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === ImportStatus::Completed;
    }

    /**
     * Check if import failed
     */
    public function isFailed(): bool
    {
        return $this->status === ImportStatus::Failed;
    }

    /**
     * Check if import is still processing
     */
    public function isProcessing(): bool
    {
        return $this->status === ImportStatus::Processing;
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return round(($this->successful_rows / $this->total_rows) * 100, 1);
    }
}
