<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketFeedback extends Model
{
    protected $table = 'ticket_feedback';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'rating',
        'comments',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    // --- RELATIONSHIPS ---

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // --- HELPERS ---

    public function getRatingLabel(): string
    {
        return match ($this->rating) {
            1 => 'Very Poor',
            2 => 'Poor',
            3 => 'Average',
            4 => 'Good',
            5 => 'Excellent',
            default => 'Unknown',
        };
    }

    public function isPositive(): bool
    {
        return $this->rating >= 4;
    }

    public function isNegative(): bool
    {
        return $this->rating <= 2;
    }

    public function isNeutral(): bool
    {
        return $this->rating === 3;
    }

    // --- VALIDATION ---

    public static function ratingOptions(): array
    {
        return [
            1 => 'Very Poor',
            2 => 'Poor',
            3 => 'Average',
            4 => 'Good',
            5 => 'Excellent',
        ];
    }
}
