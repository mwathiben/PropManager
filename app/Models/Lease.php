<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Lease extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'landlord_id',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit_amount',
        'service_charge',
        'wallet_balance',
        'is_active',
        'lease_doc_path',
        'deposit_status',
        'deposit_refund_amount',
        'deposit_deductions',
        'deposit_deduction_reason',
        'deposit_processed_at',
        'deposit_processed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'rent_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'deposit_refund_amount' => 'decimal:2',
        'deposit_deductions' => 'decimal:2',
        'deposit_processed_at' => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    // --- THIS WAS MISSING ---
    public function rentHistory()
    {
        // Note the lowercase 'r' in rentHistory matches what we called in the Route
        return $this->hasMany(RentHistory::class)->orderBy('effective_date', 'desc');
    }

    /**
     * Get all documents associated with this lease
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get all wallet transactions for this lease
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all deposit transactions for this lease
     */
    public function depositTransactions(): HasMany
    {
        return $this->hasMany(DepositTransaction::class)->orderBy('created_at', 'desc');
    }

    // --- WALLET METHODS ---

    public function hasWalletBalance(): bool
    {
        return $this->wallet_balance > 0;
    }

    public function creditToWallet(float $amount, ?string $reason = null, ?int $paymentId = null): void
    {
        throw_unless(DB::transactionLevel() > 0, \LogicException::class, 'creditToWallet must be called within a transaction');

        $locked = static::lockForUpdate()->find($this->id);
        $newBalance = (float) $locked->wallet_balance + $amount;

        $locked->update(['wallet_balance' => $newBalance]);

        WalletTransaction::create([
            'lease_id' => $this->id,
            'landlord_id' => $this->landlord_id,
            'type' => 'credit',
            'amount' => $amount,
            'reason' => $reason ?? 'Overpayment credit',
            'balance_after' => $newBalance,
            'payment_id' => $paymentId,
        ]);

        $this->wallet_balance = $newBalance;
    }

    public function deductFromWallet(float $amount, ?string $reason = null, ?int $invoiceId = null): float
    {
        throw_unless(DB::transactionLevel() > 0, \LogicException::class, 'deductFromWallet must be called within a transaction');

        $locked = static::lockForUpdate()->find($this->id);
        $deducted = min($amount, (float) $locked->wallet_balance);

        if ($deducted > 0) {
            $newBalance = (float) $locked->wallet_balance - $deducted;
            $locked->update(['wallet_balance' => $newBalance]);

            WalletTransaction::create([
                'lease_id' => $this->id,
                'landlord_id' => $this->landlord_id,
                'type' => 'debit',
                'amount' => $deducted,
                'reason' => $reason ?? 'Applied to invoice',
                'balance_after' => $newBalance,
                'invoice_id' => $invoiceId,
            ]);

            $this->wallet_balance = $newBalance;
        }

        return $deducted;
    }

    /**
     * Get the lease agreement document specifically
     */
    public function leaseAgreement()
    {
        return $this->morphOne(Document::class, 'documentable')
            ->where('document_type', 'lease_agreement')
            ->latest();
    }

    // --- TENANT MODULE RELATIONSHIPS ---

    /**
     * Get all verification statuses for this lease
     */
    public function verifications()
    {
        return $this->hasMany(TenantVerification::class);
    }

    /**
     * Get the payment verification for this lease
     */
    public function paymentVerification()
    {
        return $this->hasOne(TenantPaymentVerification::class);
    }

    /**
     * Check if payment is verified for this lease
     */
    public function hasVerifiedPayment(): bool
    {
        $verification = $this->paymentVerification;

        return ! $verification || $verification->isVerified();
    }

    /**
     * Get the move-out record for this lease
     */
    public function moveOut()
    {
        return $this->hasOne(MoveOut::class);
    }

    /**
     * Check if tenant is fully verified
     */
    public function isVerified(): bool
    {
        $verifications = $this->verifications;

        if ($verifications->isEmpty()) {
            return false;
        }

        // All verifications must be 'verified' status
        return $verifications->every(fn ($v) => $v->status === 'verified');
    }

    /**
     * Get verification progress percentage
     */
    public function getVerificationProgressAttribute(): int
    {
        $verifications = $this->verifications;

        if ($verifications->isEmpty()) {
            return 0;
        }

        $verified = $verifications->where('status', 'verified')->count();

        return (int) round(($verified / $verifications->count()) * 100);
    }

    /**
     * Check if move-out is in progress
     */
    public function hasMoveOutInProgress(): bool
    {
        return $this->moveOut &&
               ! in_array($this->moveOut->status, ['completed', 'cancelled']);
    }

    /**
     * Check if this lease has ended
     */
    public function hasEnded(): bool
    {
        return ! $this->is_active ||
               ($this->end_date && $this->end_date->isPast());
    }

    /**
     * Check if first invoice is still pending (no invoices created yet)
     */
    public function isFirstInvoicePending(): bool
    {
        return ! $this->invoices()->exists();
    }
}
