<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationQueue extends Model
{
    use HasFactory;

    protected $table = 'bank_reconciliation_queue';

    protected $fillable = [
        'landlord_id',
        'payment_id',
        'bank_code',
        'transaction_reference',
        'amount',
        'status',
        'matched_invoice_id',
        'error_message',
        'raw_payload',
        'matched_at',
        'retry_count',
        'next_retry_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_payload' => 'array',
        'matched_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function matchedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'matched_invoice_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnmatched($query)
    {
        return $query->where('status', 'unmatched');
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'error')
            ->where('retry_count', '<', 3)
            ->where('next_retry_at', '<=', now());
    }

    public function scopeForBank($query, string $bankCode)
    {
        return $query->where('bank_code', $bankCode);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function canRetry(): bool
    {
        return $this->status === 'error'
            && $this->retry_count < 3
            && ($this->next_retry_at === null || $this->next_retry_at->isPast());
    }

    public function markAsMatched(Invoice $invoice, Payment $payment): void
    {
        $this->update([
            'status' => 'matched',
            'matched_invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'matched_at' => now(),
        ]);
    }

    public function markAsError(string $message): void
    {
        $this->update([
            'status' => 'error',
            'error_message' => $message,
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => now()->addMinutes(5 * ($this->retry_count + 1)),
        ]);
    }

    public function markAsUnmatched(): void
    {
        $this->update(['status' => 'unmatched']);
    }
}
