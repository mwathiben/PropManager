<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use TenantScope;

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'landlord_id',
        'amount',
        'status',
        'reason',
        'payment_method',
        'paystack_refund_reference',
        'mpesa_conversation_id',
        'mpesa_transaction_id',
        'initiated_by',
        'approved_by',
        'processed_at',
        'notes',
        'error_details',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'error_details' => 'array',
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

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canProcess(): bool
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(?string $transactionId = null): void
    {
        $data = [
            'status' => 'completed',
            'processed_at' => now(),
        ];

        if ($transactionId && $this->payment_method === 'mobile_money') {
            $data['mpesa_transaction_id'] = $transactionId;
        }

        $this->update($data);
    }

    public function markAsFailed(array $errorDetails): void
    {
        $this->update([
            'status' => 'failed',
            'error_details' => $errorDetails,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
