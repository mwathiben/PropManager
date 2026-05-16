<?php

declare(strict_types=1);

namespace App\Services\Vendors;

/**
 * Phase-39 VENDOR-ANALYTICS-1: contract that every analytics-vendor
 * adapter (PostHog, Mixpanel, Amplitude, Heap, etc.) must satisfy.
 * Callers (AnalyticsReplayBatch + future real-time hooks) target
 * the interface, not a concrete vendor — swappable via container
 * binding in AppServiceProvider based on config('vendors.*').
 *
 * Each event is a structured array with at minimum:
 *   - distinct_id: string  (vendor's user identifier — landlord_id or user_id)
 *   - event:       string  (event name)
 *   - properties:  array   (event payload — vendors flatten this opaquely)
 *   - timestamp:   string  (ISO 8601 instant of the event)
 *
 * The return shape standardizes accept/reject counts so the caller can
 * emit metrics regardless of which vendor is wired.
 */
interface AnalyticsForwarderInterface
{
    /**
     * Flush a batch of events to the vendor.
     *
     * @param  array<int, array{distinct_id: string, event: string, properties: array, timestamp: string}>  $events
     * @return array{accepted: int, rejected: int, retryable: int, vendor: string}
     */
    public function flush(array $events): array;

    /**
     * Vendor identifier (lowercase, used as the {vendor} label on
     * analytics_events_forwarded_total counter).
     */
    public function vendor(): string;
}
