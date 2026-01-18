/**
 * Laravel Echo Composable
 * Provides reactive WebSocket connection state and channel subscription management
 */

import { ref, readonly, computed, onUnmounted, type Ref, type DeepReadonly, type ComputedRef } from 'vue';
import type Echo from 'laravel-echo';
import type { Channel } from 'laravel-echo';

type ConnectionState = 'connected' | 'connecting' | 'disconnected' | 'reconnecting';

const FALLBACK_THRESHOLD_MS = 30000;
const isDev = import.meta.env.DEV;

function log(message: string, ...args: unknown[]) {
    if (isDev) {
        console.log(`[useEcho] ${message}`, ...args);
    }
}

export interface UseEchoOptions {
    autoReconnect?: boolean;
    maxReconnectAttempts?: number;
    reconnectInterval?: number;
}

export interface UseEchoReturn {
    connectionState: DeepReadonly<Ref<ConnectionState>>;
    isConnected: DeepReadonly<Ref<boolean>>;
    connectionError: DeepReadonly<Ref<string | null>>;
    reconnectAttemptCount: DeepReadonly<Ref<number>>;
    disconnectedSince: DeepReadonly<Ref<number | null>>;
    shouldUseFallback: ComputedRef<boolean>;
    maxReconnectAttempts: number;
    subscribe: <T = unknown>(channel: string, event: string, callback: (data: T) => void) => void;
    subscribePrivate: <T = unknown>(channel: string, event: string, callback: (data: T) => void) => void;
    unsubscribe: (channel: string) => void;
    leaveAll: () => void;
    manualReconnect: () => void;
}

const activeChannels = new Map<string, Channel>();

export function useEcho(options: UseEchoOptions = {}): UseEchoReturn {
    const {
        autoReconnect = true,
        maxReconnectAttempts = 5,
        reconnectInterval = 3000,
    } = options;

    const connectionState = ref<ConnectionState>('connecting');
    const isConnected = ref(false);
    const connectionError = ref<string | null>(null);
    const reconnectAttemptCount = ref(0);
    const disconnectedSince = ref<number | null>(null);
    let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
    let fallbackCheckTimer: ReturnType<typeof setInterval> | null = null;

    const shouldUseFallback = computed(() => {
        if (disconnectedSince.value === null) return false;
        return Date.now() - disconnectedSince.value >= FALLBACK_THRESHOLD_MS;
    });

    const getEcho = (): Echo<'reverb'> | null => {
        if (typeof window !== 'undefined' && window.Echo) {
            return window.Echo;
        }
        return null;
    };

    const setupConnectionListeners = () => {
        const echo = getEcho();
        if (!echo?.connector?.pusher) return;

        const pusher = echo.connector.pusher;

        pusher.connection.bind('connected', () => {
            log('Connected to WebSocket server');
            connectionState.value = 'connected';
            isConnected.value = true;
            connectionError.value = null;
            reconnectAttemptCount.value = 0;
            disconnectedSince.value = null;
            stopFallbackCheck();
        });

        pusher.connection.bind('connecting', () => {
            log('Connecting to WebSocket server...');
            connectionState.value = 'connecting';
            isConnected.value = false;
        });

        pusher.connection.bind('disconnected', () => {
            log('Disconnected from WebSocket server');
            connectionState.value = 'disconnected';
            isConnected.value = false;

            if (disconnectedSince.value === null) {
                disconnectedSince.value = Date.now();
                startFallbackCheck();
            }

            if (autoReconnect && reconnectAttemptCount.value < maxReconnectAttempts) {
                scheduleReconnect();
            }
        });

        pusher.connection.bind('error', (error: { error?: { data?: { code?: number; message?: string } } }) => {
            const errorMessage = error?.error?.data?.message ?? 'Connection error';
            log('Connection error:', errorMessage);
            connectionError.value = errorMessage;
            connectionState.value = 'disconnected';
            isConnected.value = false;

            if (disconnectedSince.value === null) {
                disconnectedSince.value = Date.now();
                startFallbackCheck();
            }
        });

        if (pusher.connection.state === 'connected') {
            connectionState.value = 'connected';
            isConnected.value = true;
        }
    };

    const scheduleReconnect = () => {
        if (reconnectTimer) return;

        connectionState.value = 'reconnecting';
        reconnectAttemptCount.value++;

        const delay = reconnectInterval * Math.pow(2, reconnectAttemptCount.value - 1);
        log(`Scheduling reconnect attempt ${reconnectAttemptCount.value}/${maxReconnectAttempts} in ${delay}ms`);

        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            const echo = getEcho();
            if (echo?.connector?.pusher) {
                echo.connector.pusher.connect();
            }
        }, delay);
    };

    const manualReconnect = () => {
        log('Manual reconnect triggered');
        reconnectAttemptCount.value = 0;
        disconnectedSince.value = null;
        stopFallbackCheck();

        const echo = getEcho();
        if (echo?.connector?.pusher) {
            echo.connector.pusher.connect();
        }
    };

    const startFallbackCheck = () => {
        if (fallbackCheckTimer) return;
        fallbackCheckTimer = setInterval(() => {
            if (shouldUseFallback.value) {
                log('Fallback threshold reached - polling mode recommended');
            }
        }, 5000);
    };

    const stopFallbackCheck = () => {
        if (fallbackCheckTimer) {
            clearInterval(fallbackCheckTimer);
            fallbackCheckTimer = null;
        }
    };

    const subscribe = <T = unknown>(
        channel: string,
        event: string,
        callback: (data: T) => void
    ): void => {
        const echo = getEcho();
        if (!echo) {
            console.warn('[useEcho] Echo not initialized');
            return;
        }

        let echoChannel = activeChannels.get(channel);
        if (!echoChannel) {
            echoChannel = echo.channel(channel);
            activeChannels.set(channel, echoChannel);
        }

        echoChannel.listen(event, callback);
    };

    const subscribePrivate = <T = unknown>(
        channel: string,
        event: string,
        callback: (data: T) => void
    ): void => {
        const echo = getEcho();
        if (!echo) {
            console.warn('[useEcho] Echo not initialized');
            return;
        }

        const channelKey = `private-${channel}`;
        let echoChannel = activeChannels.get(channelKey);
        if (!echoChannel) {
            echoChannel = echo.private(channel);
            activeChannels.set(channelKey, echoChannel);
        }

        echoChannel.listen(event, callback);
    };

    const unsubscribe = (channel: string): void => {
        const echo = getEcho();
        if (!echo) return;

        echo.leave(channel);
        activeChannels.delete(channel);
        activeChannels.delete(`private-${channel}`);
    };

    const leaveAll = (): void => {
        const echo = getEcho();
        if (!echo) return;

        activeChannels.forEach((_, channel) => {
            echo.leave(channel.replace(/^private-/, ''));
        });
        activeChannels.clear();
    };

    setupConnectionListeners();

    onUnmounted(() => {
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
        }
        stopFallbackCheck();
    });

    return {
        connectionState: readonly(connectionState),
        isConnected: readonly(isConnected),
        connectionError: readonly(connectionError),
        reconnectAttemptCount: readonly(reconnectAttemptCount),
        disconnectedSince: readonly(disconnectedSince),
        shouldUseFallback,
        maxReconnectAttempts,
        subscribe,
        subscribePrivate,
        unsubscribe,
        leaveAll,
        manualReconnect,
    };
}
