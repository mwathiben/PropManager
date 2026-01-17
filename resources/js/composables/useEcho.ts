/**
 * Laravel Echo Composable
 * Provides reactive WebSocket connection state and channel subscription management
 */

import { ref, readonly, onUnmounted, type Ref, type DeepReadonly } from 'vue';
import type Echo from 'laravel-echo';
import type { Channel } from 'laravel-echo';

type ConnectionState = 'connected' | 'connecting' | 'disconnected' | 'reconnecting';

export interface UseEchoOptions {
    autoReconnect?: boolean;
    maxReconnectAttempts?: number;
    reconnectInterval?: number;
}

export interface UseEchoReturn {
    connectionState: DeepReadonly<Ref<ConnectionState>>;
    isConnected: DeepReadonly<Ref<boolean>>;
    connectionError: DeepReadonly<Ref<string | null>>;
    subscribe: <T = unknown>(channel: string, event: string, callback: (data: T) => void) => void;
    subscribePrivate: <T = unknown>(channel: string, event: string, callback: (data: T) => void) => void;
    unsubscribe: (channel: string) => void;
    leaveAll: () => void;
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
    let reconnectAttempts = 0;
    let reconnectTimer: ReturnType<typeof setTimeout> | null = null;

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
            connectionState.value = 'connected';
            isConnected.value = true;
            connectionError.value = null;
            reconnectAttempts = 0;
        });

        pusher.connection.bind('connecting', () => {
            connectionState.value = 'connecting';
            isConnected.value = false;
        });

        pusher.connection.bind('disconnected', () => {
            connectionState.value = 'disconnected';
            isConnected.value = false;

            if (autoReconnect && reconnectAttempts < maxReconnectAttempts) {
                scheduleReconnect();
            }
        });

        pusher.connection.bind('error', (error: { error?: { data?: { code?: number; message?: string } } }) => {
            connectionError.value = error?.error?.data?.message ?? 'Connection error';
            connectionState.value = 'disconnected';
            isConnected.value = false;
        });

        if (pusher.connection.state === 'connected') {
            connectionState.value = 'connected';
            isConnected.value = true;
        }
    };

    const scheduleReconnect = () => {
        if (reconnectTimer) return;

        connectionState.value = 'reconnecting';
        reconnectAttempts++;

        const delay = reconnectInterval * Math.pow(2, reconnectAttempts - 1);

        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            const echo = getEcho();
            if (echo?.connector?.pusher) {
                echo.connector.pusher.connect();
            }
        }, delay);
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
    });

    return {
        connectionState: readonly(connectionState),
        isConnected: readonly(isConnected),
        connectionError: readonly(connectionError),
        subscribe,
        subscribePrivate,
        unsubscribe,
        leaveAll,
    };
}
