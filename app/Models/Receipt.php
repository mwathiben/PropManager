<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use TenantScope;

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'lease_id',
        'landlord_id',
        'receipt_number',
        'amount',
        'payment_method',
        'reference',
        'notes',
        'is_partial',
        'issued_at',
        'emailed_at',
        'pdf_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_partial' => 'boolean',
        'issued_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function markAsEmailed(): void
    {
        $this->update(['emailed_at' => now()]);
    }

    public function wasEmailed(): bool
    {
        return $this->emailed_at !== null;
    }

    public function hasPdf(): bool
    {
        return ! empty($this->pdf_path);
    }
}
