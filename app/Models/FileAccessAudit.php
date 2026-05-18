<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Phase-59 ACCESS-AUDIT-1: PII-bearing file downloads audit trail.
 * Every download of a Document / Lease / WaterReading photo / KYC
 * submission lands a row here via FileAccessRecorder. TenantScope
 * keeps per-landlord queries isolated.
 *
 * Retention: 90d via FileRetentionPolicy subject=file_access_audit.
 */
class FileAccessAudit extends Model
{
    use TenantScope;

    public const ACTION_DOWNLOAD = 'download';

    public const ACTION_VIEW = 'view';

    public const ACTION_SIGNED_URL_ISSUED = 'signed_url_issued';

    protected $fillable = [
        'user_id',
        'landlord_id',
        'subject_type',
        'subject_id',
        'action',
        'ip_address',
        'user_agent',
        'accessed_path',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
