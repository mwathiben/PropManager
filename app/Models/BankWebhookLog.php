<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_code',
        'event_type',
        'payload',
        'status',
        'error_details',
        'ip_address',
        'processed_payment_id',
    ];

    // LEAK-1: webhook payloads include bank account numbers, sender
    // names, and reference fields that are PII / financial data. The
    // 'encrypted:array' cast wraps them at rest so a DB backup leak
    // does not expose raw transaction details. Reads/writes go through
    // Crypt transparently.
    protected $casts = [
        'payload' => 'encrypted:array',
    ];

    public function processedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'processed_payment_id');
    }

    public function scopeForBank($query, string $bankCode)
    {
        return $query->where('bank_code', $bankCode);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsSuccess(?Payment $payment = null): void
    {
        $this->update([
            'status' => 'success',
            'processed_payment_id' => $payment?->id,
        ]);
    }

    public function markAsError(string $errorDetails): void
    {
        $this->update([
            'status' => 'error',
            'error_details' => $errorDetails,
        ]);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }
}
