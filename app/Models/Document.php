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
        // Phase-82 DOC-META-1: property-domain types that typically carry an expiry.
        'insurance' => 'Insurance Policy',
        'compliance_cert' => 'Compliance Certificate',
        'title_deed' => 'Title Deed',
        'inspection_report' => 'Inspection Report',
        'notice' => 'Notice',
        'other' => 'Other',
    ];

    public const SCAN_PENDING = 'pending';

    public const SCAN_CLEAN = 'clean';

    public const SCAN_INFECTED = 'infected';

    public const SCAN_ERROR = 'error';

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
        'issue_date',
        'expires_at',
        'superseded_by_document_id',
        'reminder_days',
        'is_renewable',
        'description',
        'uploaded_by',
        'scan_status',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'issue_date' => 'date',
        'expires_at' => 'date',
        'reminder_days' => 'integer',
        'is_renewable' => 'boolean',
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

    /**
     * Phase-82 DOC-META-1: the fresh document that replaced this one (renewal).
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'superseded_by_document_id');
    }

    public function supersedes(): HasMany
    {
        return $this->hasMany(Document::class, 'superseded_by_document_id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by_document_id !== null;
    }

    /**
     * Phase-82 DOC-RENEWAL: the current (not-yet-superseded) documents.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_by_document_id');
    }

    /**
     * Phase-82 DOC-EXPIRY-4 / DOC-REMINDERS-1: renewable, current documents whose
     * expiry falls within their own reminder window (per-doc reminder_days, else
     * the default). Negative remaining days = already expired.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeDueForReminder(Builder $query, int $defaultDays = 30): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->whereNull('superseded_by_document_id')
            ->where('is_renewable', true)
            ->whereRaw('expires_at <= DATE_ADD(CURDATE(), INTERVAL COALESCE(reminder_days, ?) DAY)', [$defaultDays]);
    }

    /**
     * Phase-82 DOC-EXPIRY-1: expiry status for the landlord surface.
     */
    public function expiryStatus(int $defaultDays = 30): string
    {
        if ($this->expires_at === null) {
            return 'none';
        }
        if ($this->expires_at->isPast()) {
            return 'expired';
        }
        $window = $this->reminder_days ?? $defaultDays;
        if ($this->expires_at->lessThanOrEqualTo(now()->addDays($window))) {
            return 'expiring_soon';
        }

        return 'valid';
    }
}
