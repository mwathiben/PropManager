<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable, TenantScope;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_VOID = 'void';

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
        'status',
        'notes',
        'sent_at',
        'viewed_at',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'due_date' => 'date',
        'billing_period_start' => 'date',
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
        return in_array($this->status, ['overdue', 'partial', 'sent'])
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
        return $this->status === self::STATUS_VOID || $this->voided_at !== null;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
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
