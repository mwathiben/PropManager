<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use Auditable, TenantScope;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_VOIDED = 'voided';

    public const REASON_OVERPAYMENT = 'overpayment';

    public const REASON_BILLING_ERROR = 'billing_error';

    public const REASON_GOODWILL = 'goodwill';

    public const REASON_DUPLICATE_CHARGE = 'duplicate_charge';

    public const REASON_SERVICE_ISSUE = 'service_issue';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'landlord_id',
        'lease_id',
        'tenant_id',
        'invoice_id',
        'applied_to_invoice_id',
        'credit_number',
        'amount',
        'applied_amount',
        'reason',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'applied_at',
        'voided_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function appliedToInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'applied_to_invoice_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function getReasonOptions(): array
    {
        return [
            self::REASON_OVERPAYMENT => 'Overpayment',
            self::REASON_BILLING_ERROR => 'Billing Error',
            self::REASON_GOODWILL => 'Goodwill Adjustment',
            self::REASON_DUPLICATE_CHARGE => 'Duplicate Charge',
            self::REASON_SERVICE_ISSUE => 'Service Issue',
            self::REASON_OTHER => 'Other',
        ];
    }

    public function getReasonLabelAttribute(): string
    {
        return self::getReasonOptions()[$this->reason] ?? $this->reason;
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->applied_amount;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeApplied(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->remaining_amount > 0;
    }

    public function approve(User $approver): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function applyToInvoice(Invoice $invoice, ?float $amount = null): float
    {
        return DB::transaction(function () use ($invoice, $amount) {
            $lockedCredit = static::lockForUpdate()->find($this->id);
            $lockedInvoice = Invoice::lockForUpdate()->find($invoice->id);

            $remainingCredit = (float) $lockedCredit->amount - (float) $lockedCredit->applied_amount;
            $amountToApply = min(
                $amount ?? $remainingCredit,
                $remainingCredit,
                $lockedInvoice->getOutstandingAmount()
            );

            if ($amountToApply <= 0) {
                return 0;
            }

            $newApplied = (float) $lockedCredit->applied_amount + $amountToApply;
            $newRemainingCredit = (float) $lockedCredit->amount - $newApplied;

            $lockedCredit->update([
                'applied_amount' => $newApplied,
                'applied_to_invoice_id' => $lockedInvoice->id,
                'applied_at' => now(),
                'status' => $newRemainingCredit <= 0 ? self::STATUS_APPLIED : self::STATUS_APPROVED,
            ]);

            $newPaid = (float) $lockedInvoice->amount_paid + $amountToApply;
            $lockedInvoice->update([
                'amount_paid' => $newPaid,
                'status' => $newPaid >= $lockedInvoice->total_due
                    ? InvoiceStatus::Paid
                    : ($newPaid > 0 ? InvoiceStatus::Partial : $lockedInvoice->status),
            ]);

            return $amountToApply;
        });
    }

    public function void(): void
    {
        $this->update([
            'status' => self::STATUS_VOIDED,
            'voided_at' => now(),
        ]);
    }

    public static function generateCreditNumber(?User $landlord = null): string
    {
        if ($landlord) {
            $setting = $landlord->invoiceSetting;
            if ($setting) {
                return $setting->getNextCreditNoteNumber();
            }
        }

        $count = self::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        return 'CN-'.now()->format('Ym').'-'.str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
