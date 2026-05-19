/**
 * Phase-64 TELEMETRY-WIRE-2: client-side gauge accumulator for the
 * three PWA telemetry signals from Phase 62. Counter values are
 * coalesced in browser memory + flushed to /api/v1/telemetry/pwa
 * via navigator.sendBeacon on visibilitychange + beforeunload.
 *
 * sendBeacon is fire-and-forget + survives page unload, which makes
 * it the right transport for telemetry that must NOT block UI.
 *
 * NEVER use fetch() for the flush — fetch is cancelled when the tab
 * navigates away or closes. sendBeacon survives.
 */

const ENDPOINT = '/api/v1/telemetry/pwa';

interface MetricBucket {
    value: number;
    labels: Record<string, string>;
}

const buckets = new Map<string, MetricBucket>();

function bucketKey(metric: string, labels: Record<string, string>): string {
    const sortedLabels = Object.keys(labels)
        .sort()
        .map((k) => `${k}=${labels[k]}`)
        .join(',');

    return `${metric}|${sortedLabels}`;
}

export function increment(
    metric: string,
    value: number = 1,
    labels: Record<string, string> = {},
): void {
    const key = bucketKey(metric, labels);
    const existing = buckets.get(key);

    if (existing) {
        existing.value += value;
    } else {
        buckets.set(key, { value, labels });
    }
}

function buildPayload(): Array<{ metric: string; value: number; labels: Record<string, string> }> {
    const payload: Array<{ metric: string; value: number; labels: Record<string, string> }> = [];

    for (const [key, bucket] of buckets.entries()) {
        const metric = key.split('|')[0];
        payload.push({
            metric,
            value: bucket.value,
            labels: bucket.labels,
        });
    }

    return payload;
}

export function flush(): void {
    if (buckets.size === 0) {
        return;
    }

    if (typeof navigator === 'undefined' || typeof navigator.sendBeacon !== 'function') {
        return;
    }

    const payload = buildPayload();

    for (const entry of payload) {
        const body = JSON.stringify(entry);
        const blob = new Blob([body], { type: 'application/json' });
        navigator.sendBeacon(ENDPOINT, blob);
    }

    buckets.clear();
}

let isRegistered = false;

export function registerPwaTelemetry(): void {
    if (typeof document === 'undefined' || isRegistered) {
        return;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flush();
        }
    });

    window.addEventListener('beforeunload', () => {
        flush();
    });

    // Wire SW -> client metric forwarding so dead-letter additions in
    // sw.ts can post a CLIENT_METRIC message into this accumulator.
    if (
        typeof navigator !== 'undefined'
        && navigator.serviceWorker
    ) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            const data = event.data;
            if (
                data
                && data.type === 'CLIENT_METRIC'
                && typeof data.metric === 'string'
                && typeof data.value === 'number'
            ) {
                increment(
                    data.metric,
                    data.value,
                    typeof data.labels === 'object' && data.labels !== null
                        ? (data.labels as Record<string, string>)
                        : {},
                );
            }
        });
    }

    isRegistered = true;
}

export const __testing__ = { buckets, bucketKey, buildPayload };
