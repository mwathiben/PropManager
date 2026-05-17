<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-42 METHODS-1: explicit user.id <-> Stripe Customer.id
 * mapping. Soft-deletes so we retain the audit trail when a
 * Stripe Customer is removed (Phase-13 DPA-3 retention policy).
 */
class StripeCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stripe_customers';

    protected $fillable = [
        'user_id',
        'stripe_customer_id',
        'default_payment_method_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
