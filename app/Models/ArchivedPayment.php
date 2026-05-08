<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArchivedPayment extends Model
{
    use TenantScope;

    protected $fillable = [
        'original_payment_id',
        'invoice_id',
        'lease_id',
        'landlord_id',
        'payout_account_id',
        'amount',
        'currency',
        'payment_method',
        'payment_date',
        'reference',
        'paystack_reference',
        'paystack_split_code',
        'is_split_payment',
        'mpesa_transaction_id',
        'mpesa_checkout_request_id',
        'intasend_transaction_id',
        'intasend_reference',
        'bank_code',
        'bank_account_number',
        'bank_transaction_id',
        'bank_transaction_date',
        'bank_reference',
        'reconciliation_status',
        'reconciliation_matched_at',
        'is_voided',
        'voided_at',
        'void_reason',
        'notes',
        'original_created_at',
        'original_updated_at',
        'archived_at',
        'related_data',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'currency' => Currency::class,
        'is_split_payment' => 'boolean',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
        'bank_transaction_date' => 'datetime',
        'reconciliation_matched_at' => 'datetime',
        'original_created_at' => 'datetime',
        'original_updated_at' => 'datetime',
        'archived_at' => 'datetime',
        'related_data' => 'array',
    ];

    public function scopeForLandlord(Builder $query, int $landlordId): Builder
    {
        return $query->where('landlord_id', $landlordId);
    }

    public function scopeArchivedBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('archived_at', [$start, $end]);
    }

    public function scopeByOriginalId(Builder $query, int $paymentId): Builder
    {
        return $query->where('original_payment_id', $paymentId);
    }
}
