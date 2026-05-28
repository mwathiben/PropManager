<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Phase-62 CONFLICT-RESOLUTION-1: thrown when a save attempt's
 * If-Match header doesn't match the current row version.
 *
 * Carries the current row + the diff between current state and the
 * incoming payload so the controller's renderer can produce a JSON 409
 * with enough context for the client's ConflictDialog to surface
 * field-level conflicts (CONFLICT-RESOLUTION-3).
 */
class WriteConflictException extends RuntimeException
{
    public function __construct(
        public readonly Model $current,
        public readonly int $currentVersion,
        public readonly array $incoming,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Write conflict — row was modified by another writer', 409, $previous);
    }

    /**
     * Compute the per-field diff between the current row state and the
     * incoming payload. Only fields present in $incoming are compared
     * (so missing fields don't surface as conflicts).
     *
     * @return array<string, array{current: mixed, incoming: mixed}>
     */
    public function diff(): array
    {
        $diff = [];
        $currentAttrs = $this->current->getAttributes();
        foreach ($this->incoming as $key => $newValue) {
            if (! array_key_exists($key, $currentAttrs)) {
                continue;
            }
            if ($currentAttrs[$key] != $newValue) {
                $diff[$key] = [
                    'current' => $currentAttrs[$key],
                    'incoming' => $newValue,
                ];
            }
        }

        return $diff;
    }
}
