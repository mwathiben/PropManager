import { ref, readonly, type Ref, type DeepReadonly } from 'vue';

const isDev = import.meta.env.DEV;

export interface ErrorContext {
    component?: string;
    action?: string;
    userId?: number;
    extra?: Record<string, unknown>;
}

export interface UseErrorHandlerReturn {
    lastError: DeepReadonly<Ref<Error | null>>;
    logError: (error: unknown, context?: ErrorContext) => void;
    logWarning: (message: string, context?: ErrorContext) => void;
    logDebug: (message: string, ...args: unknown[]) => void;
    clearError: () => void;
}

const lastError = ref<Error | null>(null);

export function useErrorHandler(): UseErrorHandlerReturn {
    function logError(error: unknown, context?: ErrorContext): void {
        const errorObj = error instanceof Error ? error : new Error(String(error));
        lastError.value = errorObj;

        const prefix = context?.component ? `[${context.component}]` : '[Error]';
        const action = context?.action ? ` ${context.action}:` : '';

        if (isDev) {
            // eslint-disable-next-line no-console
            console.error(`${prefix}${action}`, errorObj, context?.extra ?? '');
        }

        // Production: Sentry integration stub
        // if (!isDev && window.Sentry) {
        //     window.Sentry.captureException(errorObj, {
        //         tags: { component: context?.component, action: context?.action },
        //         extra: context?.extra,
        //     });
        // }
    }

    function logWarning(message: string, context?: ErrorContext): void {
        const prefix = context?.component ? `[${context.component}]` : '[Warning]';
        const action = context?.action ? ` ${context.action}:` : '';

        if (isDev) {
            // eslint-disable-next-line no-console
            console.warn(`${prefix}${action}`, message, context?.extra ?? '');
        }
    }

    function logDebug(message: string, ...args: unknown[]): void {
        if (isDev) {
            // eslint-disable-next-line no-console
            console.log(`[Debug] ${message}`, ...args);
        }
    }

    function clearError(): void {
        lastError.value = null;
    }

    return {
        lastError: readonly(lastError),
        logError,
        logWarning,
        logDebug,
        clearError,
    };
}
