<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use Auditable, TenantScope;

    protected $fillable = [
        'invoice_id',
        'lease_id',
        'landlord_id',
        'payout_account_id',
        'amount',
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
        'notes',
        'is_voided',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'is_split_payment' => 'boolean',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function payoutAccount()
    {
        return $this->belongsTo(LandlordPayoutAccount::class, 'payout_account_id');
    }

    public function platformFee()
    {
        return $this->hasOne(PlatformFee::class);
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    public function intaSendTransaction()
    {
        return $this->hasOne(IntaSendTransaction::class);
    }
}
