<?php

declare(strict_types=1);

namespace App\Services\Sre;

use Illuminate\Support\Facades\DB;

/**
 * Phase-57 READ-REPLICAS-2: explicit fresh-read helper.
 *
 * Read replicas typically lag primary by <1s; under catch-up load
 * seconds. For write-then-immediate-read flows where a 0-result read is
 * suspicious, this helper retries inside a DB::transaction (which routes
 * to primary) when:
 *   - the first read returned empty AND
 *   - the connection's `recordsModified` flag indicates a write happened
 *     earlier in the request.
 *
 * Today the readOnly() marker is a no-op (Laravel has no per-query
 * sticky override). When sticky=false is enabled or a custom resolver
 * ships in a later phase, this helper's design is forward-compatible.
 *
 * Usage:
 *   $payment = app(ConnectionRouter::class)->ensureFreshRead(
 *       fn () => Payment::query()->readOnly()->where('id', $id)->first(),
 *   );
 */
class ConnectionRouter
{
    /**
     * Run the query closure against the read pool (via the readOnly() macro
     * inside the closure); if the result is empty AND a write was made
     * recently in this request, retry against the primary.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $queryFactory
     * @param  int  $maxStalenessMs  Ignored when sticky=true; documented for forward-compat with sticky=false setups.
     * @return TResult
     */
    public function ensureFreshRead(callable $queryFactory, int $maxStalenessMs = 1000)
    {
        $first = $queryFactory();

        $isEmpty = $first === null
            || $first === []
            || (is_object($first) && method_exists($first, 'isEmpty') && $first->isEmpty());

        if (! $isEmpty) {
            return $first;
        }

        $connection = DB::connection();
        $recentlyWrote = method_exists($connection, 'getRecordsModified') && $connection->getRecordsModified();
        if (! $recentlyWrote) {
            return $first;
        }

        // Fallback: run the closure again, but the replica routing context
        // is per-query. The closure rebuilds the Eloquent Builder fresh
        // each call, so simply re-invoking it without the readOnly() hint
        // would still send to the read pool — Laravel's sticky kicks in.
        // To force primary, wrap the closure in onWriteConnection().
        return DB::transaction(function () use ($queryFactory) {
            return $queryFactory();
        });
    }
}
