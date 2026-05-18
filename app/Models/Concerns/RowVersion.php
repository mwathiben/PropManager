<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\WriteConflictException;

/**
 * Phase-62 CONFLICT-RESOLUTION-1: opt-in optimistic concurrency for
 * mutable resources whose offline replay can race with concurrent
 * edits.
 *
 * Usage on a model:
 *
 *   use RowVersion;
 *   // version column declared in $fillable + migration adds the column
 *
 * On every save the trait bumps the version. Controllers handling the
 * write path call assertIfMatch($incomingVersion) BEFORE saving; the
 * trait throws WriteConflictException when versions diverge.
 */
trait RowVersion
{
    public static function bootRowVersion(): void
    {
        static::saving(function ($model) {
            if (! $model->exists) {
                $model->version = $model->version ?? 1;
                return;
            }
            $model->version = ($model->version ?? 1) + 1;
        });
    }

    /**
     * Throw WriteConflictException if $incomingVersion doesn't match
     * the current row version. Null skips the check (backwards
     * compatible for callers that haven't opted in yet).
     */
    public function assertIfMatch(?int $incomingVersion, array $incomingPayload = []): void
    {
        if ($incomingVersion === null) {
            return;
        }
        $currentVersion = (int) ($this->version ?? 0);
        if ($incomingVersion !== $currentVersion) {
            throw new WriteConflictException(
                current: $this,
                currentVersion: $currentVersion,
                incoming: $incomingPayload,
            );
        }
    }
}
