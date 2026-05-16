<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Traits\Auditable;
use App\Traits\EnforcesAccountingPeriodLock;
use App\Traits\TenantScope;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Auditable, EnforcesAccountingPeriodLock, HasFactory, SoftDeletes, TenantScope;

    protected function accountingPeriodDateColumn(): string
    {
        return 'created_at';
    }

    /**
     * Phase-13 DPA-3: invoices are processed on the lawful basis of
     * contract performance — the underlying lease defines the
     * payment obligation that an invoice realises. The 7-year
     * retention obligation lives on Payment (legal_obligation); an
     * invoice without an attached payment is purely contractual.
     */
    public function getLawfulBasis(): string
    {
        return 'contract';
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', InvoiceStatus::Overdue);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue]);
    }

    public function scopeOutstanding($query)
    {
        return $query->whereRaw('amount_paid < total_due');
    }

    public function scopePaid($query)
    {
        return $query->where('status', InvoiceStatus::Paid);
    }

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'invoice_type_id',
        'invoice_template_id',
        'credit_note_for_id',
        'invoice_number',
        'due_date',
        'billing_period_start',
        'rent_due',
        'water_due',
        'arrears',
        'late_fees_total',
        'late_fees_waived',
        'wallet_applied',
        'total_due',
        'amount_paid',
        'currency',
        'status',
        'notes',
        'pdf_path',
        'pdf_generated_at',
        'sent_at',
        'viewed_at',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'currency' => Currency::class,
        'due_date' => 'date',
        'billing_period_start' => 'date',
        'pdf_generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function getBillingPeriodAttribute(): ?\Carbon\Carbon
    {
        return $this->billing_period_start;
    }

    public function getBillingPeriodEndAttribute(): ?\Carbon\Carbon
    {
        return $this->billing_period_start?->copy()->endOfMonth();
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function invoiceType(): BelongsTo
    {
        return $this->belongsTo(InvoiceType::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'invoice_template_id');
    }

    public function creditNoteFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_note_for_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'credit_note_for_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function lateFees(): HasMany
    {
        return $this->hasMany(LateFee::class);
    }

    public function activeLateFees(): HasMany
    {
        return $this->hasMany(LateFee::class)->where('is_waived', false);
    }

    public function recalculateLateFees(): void
    {
        // Phase-17 MONEY-1/2: bcmath-backed Money arithmetic replaces the
        // (float) X + (float) Y chain. Cumulative drift across compounded
        // late-fee updates persists in the total_due column — using Money
        // here eliminates that drift at the canonical write site.
        $activeTotal = Money::fromString((string) $this->lateFees()->where('is_waived', false)->sum('fee_amount'));
        $waivedTotal = Money::fromString((string) $this->lateFees()->where('is_waived', true)->sum('fee_amount'));

        $totalDue = Money::fromString((string) $this->rent_due)
            ->add(Money::fromString((string) $this->water_due))
            ->add(Money::fromString((string) $this->arrears))
            ->add($activeTotal)
            ->subtract(Money::fromString((string) ($this->wallet_applied ?? '0')))
            ->clampPositive();

        $this->update([
            'late_fees_total' => $activeTotal->toDecimalString(),
            'late_fees_waived' => $waivedTotal->toDecimalString(),
            'total_due' => $totalDue->toDecimalString(),
        ]);
    }

    /**
     * Phase-17 MONEY-1/2: bcmath-backed Money replaces the (float) X -
     * (float) Y pattern. Caller-facing return is still float for
     * backwards compatibility with the controllers and Inertia
     * serialization; the float is generated from the exact string at
     * the boundary so any drift is bounded to a single conversion
     * (vs. accumulating across every read).
     */
    public function getOutstandingAmount(): float
    {
        return $this->getOutstandingMoney()->toFloatLossy();
    }

    public function getOutstandingMoney(): Money
    {
        return Money::fromString((string) $this->total_due)
            ->subtract(Money::fromString((string) $this->amount_paid))
            ->clampPositive();
    }

    public function isEligibleForLateFee(): bool
    {
        return in_array($this->status, [InvoiceStatus::Overdue, InvoiceStatus::Partial, InvoiceStatus::Sent])
            && $this->due_date
            && $this->due_date->isPast()
            && $this->getOutstandingAmount() > 0;
    }

    public function isCreditNote(): bool
    {
        return $this->invoiceType?->isCredit() ?? false;
    }

    public function isVoid(): bool
    {
        return $this->status === InvoiceStatus::Voided || $this->voided_at !== null;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => InvoiceStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function markAsViewed(): void
    {
        if (! $this->viewed_at) {
            $this->update(['viewed_at' => now()]);
        }
    }
}
