<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositTransaction extends Model
{
    use Auditable, TenantScope;

    /**
     * AUDIT-6: capture deposit-transaction context (type/amount/balance/processed_by)
     * on every audit row so deductions, forfeits and refunds are traceable
     * without joining back to deposit_transactions.
     */
    public function getAuditMetadata(): array
    {
        return [
            'type' => $this->type,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'processed_by' => $this->processed_by,
            'lease_id' => $this->lease_id,
            'move_out_id' => $this->move_out_id,
        ];
    }

    public const TYPE_RECEIVED = 'received';

    public const TYPE_PARTIAL_REFUND = 'partial_refund';

    public const TYPE_FULL_REFUND = 'full_refund';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_FORFEIT = 'forfeit';

    public const TYPE_TRANSFER = 'transfer';

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'processed_by',
        'type',
        'amount',
        'balance_after',
        'reason',
        'notes',
        'payment_method',
        'reference',
        'move_out_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function moveOut(): BelongsTo
    {
        return $this->belongsTo(MoveOut::class);
    }

    public function isRefund(): bool
    {
        return in_array($this->type, [self::TYPE_PARTIAL_REFUND, self::TYPE_FULL_REFUND]);
    }

    public function isDebit(): bool
    {
        return in_array($this->type, [self::TYPE_DEDUCTION, self::TYPE_FORFEIT, self::TYPE_PARTIAL_REFUND, self::TYPE_FULL_REFUND]);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_RECEIVED => 'Deposit Received',
            self::TYPE_PARTIAL_REFUND => 'Partial Refund',
            self::TYPE_FULL_REFUND => 'Full Refund',
            self::TYPE_DEDUCTION => 'Deduction',
            self::TYPE_FORFEIT => 'Forfeited',
            self::TYPE_TRANSFER => 'Transferred',
            default => ucfirst($this->type),
        };
    }
}
