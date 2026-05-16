<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase-29 WF-RENT-REMIND-1: a landlord-scoped rent reminder cadence
 * policy. Cadence_template names a curated template; resolveOffsets()
 * returns the integer-array of signed day offsets relative to invoice
 * due_date. Negative = days BEFORE due_date, positive = AFTER.
 */
class RentReminderPolicy extends Model
{
    use TenantScope;

    public const TEMPLATE_STANDARD = 'standard';

    public const TEMPLATE_AGGRESSIVE = 'aggressive';

    public const TEMPLATE_LENIENT = 'lenient';

    public const TEMPLATE_CUSTOM = 'custom';

    /**
     * @var array<string, array<int, int>>
     */
    public const TEMPLATE_OFFSETS = [
        self::TEMPLATE_STANDARD => [0, 3, 7],
        self::TEMPLATE_AGGRESSIVE => [-3, 0, 1, 5],
        self::TEMPLATE_LENIENT => [5, 10],
    ];

    protected $fillable = [
        'landlord_id',
        'name',
        'cadence_template',
        'offsets_json',
        'channels',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'offsets_json' => 'array',
        'channels' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Resolve the signed-day-offset array used by RentRemindersDispatch.
     *
     * @return array<int, int>
     */
    public function resolveOffsets(): array
    {
        if ($this->cadence_template === self::TEMPLATE_CUSTOM) {
            return is_array($this->offsets_json) ? array_values($this->offsets_json) : [];
        }

        return self::TEMPLATE_OFFSETS[$this->cadence_template] ?? [];
    }
}
