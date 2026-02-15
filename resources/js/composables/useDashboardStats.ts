import { ref, readonly, watch, onUnmounted, type Ref, type ComputedRef, type DeepReadonly } from 'vue';
import { router } from '@inertiajs/vue3';
import axios, { type AxiosError } from 'axios';
import { useErrorHandler } from './useErrorHandler';
import type { FinancialMetrics, ArrearsAging } from '@/types';

const POLL_INTERVAL_MS = 30_000;
const SMART_REFRESH_DELAY_MS = 1_500;

export interface DashboardStatsData {
    financial: FinancialMetrics;
    arrears_aging: ArrearsAging;
    action_items: {
        overdue_invoices: number;
        overdue_amount: number;
        open_tickets: number;
    };
}

export interface UseDashboardStatsOptions {
    shouldUseFallback: ComputedRef<boolean>;
    isConnected: DeepReadonly<Ref<boolean>>;
}

export interface UseDashboardStatsReturn {
    latestStats: Ref<DashboardStatsData | null>;
    isPolling: DeepReadonly<Ref<boolean>>;
    lastPollAt: DeepReadonly<Ref<number | null>>;
    pollNow: () => void;
}

export function useDashboardStats(options: UseDashboardStatsOptions): UseDashboardStatsReturn {
    const { shouldUseFallback, isConnected } = options;
    const { logError, logWarning, logDebug } = useErrorHandler();

    const latestStats = ref<DashboardStatsData | null>(null);
    const isPolling = ref(false);
    const lastPollAt = ref<number | null>(null);

    let pollTimer: ReturnType<typeof setInterval> | null = null;
    let refreshTimer: ReturnType<typeof setTimeout> | null = null;
    let rateLimitedUntil = 0;

    async function fetchStats(): Promise<void> {
        if (Date.now() < rateLimitedUntil) {
            logDebug('Dashboard stats fetch skipped — rate limited');
            return;
        }

        try {
            const response = await axios.get<DashboardStatsData>('/dashboard/stats');
            latestStats.value = response.data;
            lastPollAt.value = Date.now();
        } catch (err) {
            const error = err as AxiosError;

            if (error.response?.status === 429) {
                const retryAfter = Number(error.response.headers['retry-after']) || 60;
                rateLimitedUntil = Date.now() + retryAfter * 1000;
                logWarning(`Dashboard stats rate limited, pausing for ${retryAfter}s`, {
                    component: 'useDashboardStats',
                    action: 'fetchStats',
                });
                return;
            }

            if (error.response?.status === 401 || error.response?.status === 419) {
                stopPolling();
                router.reload();
                return;
            }

            logError(error, { component: 'useDashboardStats', action: 'fetchStats' });
        }
    }

    function startPolling(): void {
        if (pollTimer) return;
        isPolling.value = true;
        logDebug('Dashboard stats polling started');
        fetchStats();
        pollTimer = setInterval(fetchStats, POLL_INTERVAL_MS);
    }

    function stopPolling(): void {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        isPolling.value = false;
    }

    function pollNow(): void {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => {
            refreshTimer = null;
            fetchStats();
        }, SMART_REFRESH_DELAY_MS);
    }

    watch(shouldUseFallback, (useFallback) => {
        if (useFallback) {
            startPolling();
        }
    }, { immediate: true });

    watch(isConnected, (connected) => {
        if (connected && isPolling.value) {
            logDebug('WebSocket reconnected — stopping polling, fetching final stats');
            stopPolling();
            fetchStats();
        }
    });

    onUnmounted(() => {
        stopPolling();
        if (refreshTimer) {
            clearTimeout(refreshTimer);
            refreshTimer = null;
        }
    });

    return {
        latestStats,
        isPolling: readonly(isPolling),
        lastPollAt: readonly(lastPollAt),
        pollNow,
    };
}
