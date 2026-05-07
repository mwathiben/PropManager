<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

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
        $activeTotal = $this->lateFees()->where('is_waived', false)->sum('fee_amount');
        $waivedTotal = $this->lateFees()->where('is_waived', true)->sum('fee_amount');

        $totalDue = (float) $this->rent_due
            + (float) $this->water_due
            + (float) $this->arrears
            + $activeTotal
            - (float) $this->wallet_applied;

        $this->update([
            'late_fees_total' => $activeTotal,
            'late_fees_waived' => $waivedTotal,
            'total_due' => max(0, $totalDue),
        ]);
    }

    public function getOutstandingAmount(): float
    {
        return max(0, (float) $this->total_due - (float) $this->amount_paid);
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
