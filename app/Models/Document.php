<?php

namespace App\Models;

use App\Models\Concerns\HasLegalHolds;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $landlord_id
 * @property int|null $documentable_id
 * @property string|null $documentable_type
 * @property string $title
 * @property string $file_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $document_type
 * @property string|null $description
 * @property int $uploaded_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read string $file_size_formatted
 * @property-read string $file_extension
 * @property-read User $landlord
 * @property-read User $uploader
 * @property-read Model|null $documentable
 */
class Document extends Model
{
    use Auditable, HasFactory, HasLegalHolds, SoftDeletes, TenantScope;

    public const DOCUMENT_TYPES = [
        'lease_agreement' => 'Lease Agreement',
        'tenant_id' => 'Tenant ID',
        'tenant_passport' => 'Tenant Passport',
        'bank_statement' => 'Bank Statement',
        'payslip' => 'Payslip',
        'reference_letter' => 'Reference Letter',
        'utility_bill' => 'Utility Bill',
        'other' => 'Other',
    ];

    protected $fillable = [
        'landlord_id',
        'documentable_id',
        'documentable_type',
        'annotates_document_id',
        'annotation_data',
        'title',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'document_type',
        'expires_at',
        'description',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'expires_at' => 'date',
        'annotation_data' => 'array',
    ];

    protected $appends = ['file_size_formatted'];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Phase-45 TICKET-PHOTOS-2: this Document's parent (the original
     * image, if this row is an annotated copy). NULL for originals.
     */
    public function annotates(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'annotates_document_id');
    }

    /**
     * Phase-45 TICKET-PHOTOS-2: annotated sibling copies of THIS document.
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(Document::class, 'annotates_document_id');
    }

    public function isAnnotation(): bool
    {
        return $this->annotates_document_id !== null;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function getFullPath(): string
    {
        return Storage::tenant()->path($this->file_path);
    }

    public function fileExists(): bool
    {
        return Storage::tenant()->exists($this->file_path);
    }

    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::tenant()->delete($this->file_path);
        }

        return false;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($document) {
            if ($document->isForceDeleting()) {
                $document->deleteFile();
            }
        });
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeForModel(Builder $query, string $modelType): Builder
    {
        return $query->where('documentable_type', 'App\\Models\\'.$modelType);
    }

    /**
     * Phase-28 TENANT-DOCS-3: documents within $days of expiry (negative
     * days_remaining means already expired). Pure scope — caller filters
     * by tenant via the documentable join.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days)->toDateString());
    }
}
