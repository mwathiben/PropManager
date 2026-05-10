<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntaSendTransaction extends Model
{
    use Auditable, HasFactory, TenantScope;

    protected $table = 'intasend_transactions';

    public const STATE_PENDING = 'PENDING';

    public const STATE_PROCESSING = 'PROCESSING';

    public const STATE_COMPLETE = 'COMPLETE';

    public const STATE_FAILED = 'FAILED';

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'landlord_id',
        'intasend_invoice_id',
        'api_ref',
        'phone_number',
        'amount',
        'intasend_charges',
        'net_amount',
        'platform_fee',
        'landlord_amount',
        'state',
        'mpesa_receipt',
        'failure_reason',
        'webhook_payload',
    ];

    // LEAK-2: webhook_payload carries the full IntaSend callback,
    // which includes phone numbers and transaction state. Encrypt at
    // rest so a DB backup leak does not expose tenant PII.
    protected $casts = [
        'amount' => 'decimal:2',
        'intasend_charges' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'landlord_amount' => 'decimal:2',
        'webhook_payload' => 'encrypted:array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function scopePending($query)
    {
        return $query->where('state', self::STATE_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('state', self::STATE_PROCESSING);
    }

    public function scopeComplete($query)
    {
        return $query->where('state', self::STATE_COMPLETE);
    }

    public function scopeFailed($query)
    {
        return $query->where('state', self::STATE_FAILED);
    }

    public function scopeForInvoice($query, int $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->state === self::STATE_PROCESSING;
    }

    public function isComplete(): bool
    {
        return $this->state === self::STATE_COMPLETE;
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }

    public function markComplete(string $mpesaReceipt): self
    {
        $this->update([
            'state' => self::STATE_COMPLETE,
            'mpesa_receipt' => $mpesaReceipt,
        ]);

        return $this;
    }

    public function markFailed(string $reason): self
    {
        $this->update([
            'state' => self::STATE_FAILED,
            'failure_reason' => $reason,
        ]);

        return $this;
    }

    public function markProcessing(): self
    {
        $this->update([
            'state' => self::STATE_PROCESSING,
        ]);

        return $this;
    }
}
