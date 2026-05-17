<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-42 CART-1: one line per item in a CheckoutSession. line_type
 * is a string discriminator + line_id is polymorphic (no FK
 * constraint because the parent table varies). amount_cents +
 * currency travel together so a single session can carry mixed
 * currencies (one KES rent line + one USD add-on line).
 */
class CheckoutSessionItem extends Model
{
    use HasFactory;

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_ADD_ON = 'add_on';

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPES = [self::TYPE_INVOICE, self::TYPE_ADD_ON, self::TYPE_DEPOSIT];

    protected $fillable = [
        'checkout_session_id',
        'line_type',
        'line_id',
        'amount_cents',
        'currency',
        'description',
        'sort_order',
        'stripe_payment_intent_id',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'sort_order' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }
}
