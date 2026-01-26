<?php

namespace App\Models;

use App\Traits\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSetting extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'business_name',
        'business_address',
        'business_phone',
        'business_email',
        'logo_path',
        'tax_number',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_branch',
        'bank_swift_code',
        'invoice_prefix',
        'invoice_next_number',
        'receipt_prefix',
        'receipt_next_number',
        'credit_note_prefix',
        'credit_note_next_number',
        'default_due_days',
        'late_penalty_percentage',
        'grace_period_days',
        'terms_and_conditions',
        'footer_note',
        'auto_generate_enabled',
        'auto_generate_day',
        'auto_send_enabled',
        'prorate_first_month',
        'include_last_month_rent',
        'admin_fee_amount',
        'key_deposit_amount',
        'first_invoice_due_days',
        'auto_generate_first_invoice',
        'auto_email_receipt',
        'receipt_show_logo',
        'receipt_show_tenant_details',
        'receipt_show_invoice_details',
        'receipt_show_payment_method',
        'receipt_header_text',
        'receipt_footer_text',
        'receipt_thank_you_message',
        'fiscal_year_type',
        'fiscal_year_start_month',
    ];

    protected $casts = [
        'late_penalty_percentage' => 'decimal:2',
        'auto_generate_enabled' => 'boolean',
        'auto_send_enabled' => 'boolean',
        'prorate_first_month' => 'boolean',
        'include_last_month_rent' => 'boolean',
        'admin_fee_amount' => 'decimal:2',
        'key_deposit_amount' => 'decimal:2',
        'auto_generate_first_invoice' => 'boolean',
        'auto_email_receipt' => 'boolean',
        'receipt_show_logo' => 'boolean',
        'receipt_show_tenant_details' => 'boolean',
        'receipt_show_invoice_details' => 'boolean',
        'receipt_show_payment_method' => 'boolean',
        'fiscal_year_start_month' => 'integer',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function getNextInvoiceNumber(): string
    {
        $number = str_pad($this->invoice_next_number, 4, '0', STR_PAD_LEFT);
        $this->increment('invoice_next_number');

        return $this->invoice_prefix.'-'.now()->format('Ym').'-'.$number;
    }

    public function getNextReceiptNumber(): string
    {
        $number = str_pad($this->receipt_next_number, 4, '0', STR_PAD_LEFT);
        $this->increment('receipt_next_number');

        return $this->receipt_prefix.'-'.now()->format('Ym').'-'.$number;
    }

    public function getNextCreditNoteNumber(): string
    {
        $number = str_pad($this->credit_note_next_number, 4, '0', STR_PAD_LEFT);
        $this->increment('credit_note_next_number');

        return $this->credit_note_prefix.'-'.now()->format('Ym').'-'.$number;
    }

    public function hasBankDetails(): bool
    {
        return ! empty($this->bank_name) && ! empty($this->bank_account_number);
    }

    public function hasBusinessDetails(): bool
    {
        return ! empty($this->business_name);
    }

    public function isCalendarYear(): bool
    {
        return $this->fiscal_year_type === 'calendar';
    }

    public function getFiscalYearStart(?Carbon $referenceDate = null): Carbon
    {
        $reference = $referenceDate ?? now();
        $startMonth = $this->fiscal_year_start_month ?? 1;

        if ($startMonth === 1) {
            return $reference->copy()->startOfYear();
        }

        $year = $reference->month >= $startMonth
            ? $reference->year
            : $reference->year - 1;

        return Carbon::create($year, $startMonth, 1)->startOfDay();
    }

    public function getFiscalYearEnd(?Carbon $referenceDate = null): Carbon
    {
        $start = $this->getFiscalYearStart($referenceDate);

        return $start->copy()->addYear()->subDay()->endOfDay();
    }

    public function getPreviousFiscalYearStart(?Carbon $referenceDate = null): Carbon
    {
        return $this->getFiscalYearStart($referenceDate)->subYear();
    }

    public function getPreviousFiscalYearEnd(?Carbon $referenceDate = null): Carbon
    {
        return $this->getFiscalYearStart($referenceDate)->subDay()->endOfDay();
    }
}
