<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'old_amount',
        'new_amount',
        'effective_date',
        'reason',
        'notification_sent',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'notification_sent' => 'boolean',
        'old_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }
}
