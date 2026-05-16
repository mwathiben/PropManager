<?php

namespace App\Models;

use App\Enums\Currency;
use App\Traits\Auditable;
use App\Traits\EnforcesAccountingPeriodLock;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    use Auditable, EnforcesAccountingPeriodLock, TenantScope;

    protected function accountingPeriodDateColumn(): string
    {
        return 'payment_date';
    }

    /**
     * Phase-13 DPA-3: payment processing has two stacked lawful bases —
     * the underlying transaction is a contract performance, but the
     * retention obligation (7-year tax & financial records) is a legal
     * obligation under Kenya tax law. We surface legal_obligation as
     * the dominant basis because it determines the longer of the two
     * retention windows.
     */
    public function getLawfulBasis(): string
    {
        return 'legal_obligation';
    }

    protected $fillable = [
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
        'notes',
        'is_voided',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'currency' => Currency::class,
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

    public function scopeArchivable(Builder $query): Builder
    {
        return $query->where('payment_date', '<', now()->subYears(
            (int) config('security.compliance.data_retention_years', 7)
        ));
    }

    /**
     * Query both active and archived payments via the all_payments view.
     * Tenant-scoped: the view includes landlord_id so TenantScope still applies.
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->from(DB::raw('all_payments as '.(new static)->getTable()));
    }
}
