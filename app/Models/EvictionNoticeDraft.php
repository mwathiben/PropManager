<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-29 WF-LATE-FEE-3: eviction notice DRAFT. The system MUST NOT
 * auto-send these — landlord must explicitly confirm via the
 * landlord-side EvictionNoticeDraftController::sendManual (Phase 2 or
 * follow-up). draft_body is pre-populated by the escalation command
 * with the standard Kenya notice template (tenant name, lease
 * address, total arrears, invoice numbers).
 */
class EvictionNoticeDraft extends Model
{
    use TenantScope;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'landlord_id',
        'lease_id',
        'tenant_id',
        'related_invoice_ids',
        'total_arrears_cents',
        'draft_body',
        'status',
        'sent_at',
        'withdrawn_at',
        'withdrawn_reason',
        'source_workflow',
    ];

    protected $casts = [
        'related_invoice_ids' => 'array',
        'total_arrears_cents' => 'integer',
        'sent_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
