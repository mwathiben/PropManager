<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClauseBinding;
use App\Enums\ClauseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Slice-2: a curated, explained, parameterised legal clause. Platform reference
 * data shared across all managers (NOT tenant-scoped). The body is a template
 * with {param} placeholders filled per agreement; the binding says what config a
 * signed instance governs.
 */
class Clause extends Model
{
    /** @use HasFactory<\Database\Factories\ClauseFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'type',
        'binding',
        'title',
        'explanation',
        'body_template',
        'params_schema',
        'is_exclusive',
        'jurisdiction',
        'version',
        'is_active',
        'needs_legal_review',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClauseType::class,
            'binding' => ClauseBinding::class,
            'params_schema' => 'array',
            'is_exclusive' => 'boolean',
            'is_active' => 'boolean',
            'needs_legal_review' => 'boolean',
        ];
    }

    /**
     * Fill {param} placeholders from the chosen params. Pure + deterministic so
     * the composed legal text is testable. An unknown or non-scalar placeholder
     * is left intact — an obviously-unfilled term is safer than a silently
     * blanked one.
     *
     * @param  array<string, mixed>  $params
     */
    public static function renderTemplate(string $template, array $params): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $match) use ($params): string {
            $key = $match[1];

            if (! array_key_exists($key, $params) || ! is_scalar($params[$key])) {
                return $match[0];
            }

            return (string) $params[$key];
        }, $template);
    }

    /**
     * @param  Builder<Clause>  $query
     * @return Builder<Clause>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
