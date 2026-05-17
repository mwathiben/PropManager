<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-45 STATEMENT-DEPTH-3: tenant-persisted column selection +
 * order for the statement export. One row per user (UNIQUE index on
 * user_id); columns is an ordered list of column keys.
 *
 * @property int $id
 * @property int $user_id
 * @property list<string> $columns
 */
class TenantStatementPreference extends Model
{
    /** @var list<string> */
    public const ALLOWED_COLUMNS = [
        'date',
        'description',
        'reference',
        'charge',
        'payment',
        'running_balance',
    ];

    /** @var list<string> */
    public const DEFAULT_COLUMNS = [
        'date',
        'description',
        'reference',
        'charge',
        'payment',
        'running_balance',
    ];

    protected $fillable = ['user_id', 'columns'];

    protected $casts = [
        'columns' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return the columns this tenant has selected (or the default
     * set if no preference row exists).
     *
     * @return list<string>
     */
    public static function columnsFor(User $user): array
    {
        $pref = static::query()->where('user_id', $user->id)->first();
        if ($pref === null) {
            return self::DEFAULT_COLUMNS;
        }
        $selected = array_values(array_filter(
            (array) $pref->columns,
            static fn ($key) => in_array($key, self::ALLOWED_COLUMNS, true),
        ));

        return $selected === [] ? self::DEFAULT_COLUMNS : $selected;
    }
}
