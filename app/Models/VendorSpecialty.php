<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-75 VENDOR-ROUTING-1: a trade a vendor handles (a Ticket issue
 * subcategory). Landlord isolation comes through the parent vendor.
 */
class VendorSpecialty extends Model
{
    protected $fillable = [
        'vendor_id',
        'category',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
