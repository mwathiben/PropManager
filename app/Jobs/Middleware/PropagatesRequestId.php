<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Phase-14 OBSERV-4: queue-job middleware that re-applies the
 * request_id from a job's payload back into Log::withContext so
 * every log line emitted while the job runs carries the original
 * HTTP request's correlation id.
 *
 * Usage: jobs that want correlation override middleware() to
 * return [new PropagatesRequestId]. The dispatching code wires
 * the id via PropagatesRequestId::stamp($job) at dispatch time.
 *
 * Without this middleware, AddRequestId's Log::withContext call
 * was process-local — once the job ran in a worker process, the
 * context was empty and the request_id was lost.
 */
class PropagatesRequestId
{
    /**
     * Read $job->requestId (set via the trait below) and re-apply it
     * to the current process's log context for the duration of the
     * job. Cleared after handle() returns so a long-running worker
     * doesn't leak the previous job's id into the next.
     */
    public function handle(object $job, Closure $next): mixed
    {
        $requestId = is_string($job->requestId ?? null) ? $job->requestId : null;
        if ($requestId !== null) {
            Log::withContext(['request_id' => $requestId, 'queue_job' => $job::class]);
        }

        try {
            return $next($job);
        } finally {
            if ($requestId !== null) {
                // No public "clear context" API; overwrite with null
                // so the next job in the same worker starts fresh.
                Log::withContext(['request_id' => null, 'queue_job' => null]);
            }
        }
    }
}
