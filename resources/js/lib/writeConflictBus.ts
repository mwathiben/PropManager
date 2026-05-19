/**
 * Phase-64 OFFLINE-MOUNTS-1: writeConflictBus event-emitter that
 * surfaces a 409 from the offline-replay layer to whichever UI
 * component is mounted to handle it (typically the global
 * ConflictDialog in AuthenticatedLayout).
 *
 * Why a bus and not a Pinia store: the conflict is a one-shot event,
 * not persisted state. Pinia would carry the resolution callback
 * across reactivity flushes; a bus discards it cleanly after dispatch.
 */

export interface WriteConflictPayload {
    queue: string;
    url: string;
    current?: Record<string, unknown>;
    incoming?: Record<string, unknown>;
    diff?: Record<string, { current: unknown; incoming: unknown }>;
    [extra: string]: unknown;
}

type Handler = (payload: WriteConflictPayload) => void;

const handlers = new Set<Handler>();

export function on(handler: Handler): () => void {
    handlers.add(handler);

    return () => {
        handlers.delete(handler);
    };
}

export function emit(payload: WriteConflictPayload): void {
    for (const handler of handlers) {
        try {
            handler(payload);
        } catch (err) {
            // Best-effort dispatch — a misbehaving handler should not
            // suppress the others.
            // eslint-disable-next-line no-console
            console.error('writeConflictBus handler threw:', err);
        }
    }
}

export function clear(): void {
    handlers.clear();
}
