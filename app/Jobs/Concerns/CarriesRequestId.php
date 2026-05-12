<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\PropagatesRequestId;

/**
 * Phase-14 OBSERV-4: trait jobs use to carry the dispatching HTTP
 * request's request_id across the queue boundary. Reading is automatic
 * via PropagatesRequestId middleware; assignment happens at dispatch
 * time:
 *
 *   dispatch((new SendInvoiceEmail($invoice))->withCurrentRequestId());
 *
 * The trait exposes:
 *   - $requestId             public property (Laravel-serialised)
 *   - withCurrentRequestId() reads request()->attributes->get('request_id')
 *   - middleware()           returns [new PropagatesRequestId]
 *
 * Jobs that already define middleware() should merge the result of
 * the trait's middleware() with their own.
 */
trait CarriesRequestId
{
    public ?string $requestId = null;

    public function withRequestId(?string $requestId): static
    {
        $this->requestId = $requestId;

        return $this;
    }

    /**
     * Pull the current request's request_id (set by AddRequestId
     * middleware) into the job. Safe to call outside an HTTP request
     * (returns the job without modification).
     */
    public function withCurrentRequestId(): static
    {
        $request = request();
        $id = $request?->attributes->get('request_id');
        if (is_string($id) && $id !== '') {
            $this->requestId = $id;
        }

        return $this;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new PropagatesRequestId];
    }
}
