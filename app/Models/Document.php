<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use Auditable, SoftDeletes, TenantScope;

    protected $fillable = [
        'landlord_id',
        'documentable_id',
        'documentable_type',
        'title',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'document_type',
        'description',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected $appends = ['file_size_formatted'];

    /**
     * Get the owning documentable model (Lease, User, etc.)
     */
    public function documentable()
    {
        return $this->morphTo();
    }

    /**
     * Get the landlord who owns this document
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get formatted file size (e.g., "2.5 MB")
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get the file extension
     */
    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if file is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get the full storage path
     */
    public function getFullPath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    /**
     * Check if file exists in storage
     */
    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    /**
     * Delete the physical file
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk('local')->delete($this->file_path);
        }

        return false;
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When deleting a document, also delete the file
        static::deleting(function ($document) {
            if ($document->isForceDeleting()) {
                $document->deleteFile();
            }
        });
    }

    /**
     * Scope: Filter by document type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope: Filter by documentable type (e.g., 'Lease', 'User')
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('documentable_type', 'App\\Models\\'.$modelType);
    }
}
