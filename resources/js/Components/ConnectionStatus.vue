<script setup lang="ts">
import { useEcho } from '@/composables/useEcho';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';

const {
    connectionState,
    isConnected,
    connectionError,
    reconnectAttemptCount,
    shouldUseFallback,
    maxReconnectAttempts,
    manualReconnect,
} = useEcho();

const { t } = useI18n();

const statusConfig = computed(() => {
    const configs = {
        connected: {
            dot: 'bg-green-500',
            label: t('connection_status.connected'),
            animate: false,
        },
        connecting: {
            dot: 'bg-amber-500',
            label: t('connection_status.connecting'),
            animate: true,
        },
        reconnecting: {
            dot: 'bg-amber-500',
            label: t('connection_status.reconnecting', {
                current: reconnectAttemptCount.value,
                max: maxReconnectAttempts,
            }),
            animate: true,
        },
        disconnected: {
            dot: 'bg-red-500',
            label: shouldUseFallback.value
                ? t('connection_status.offline')
                : t('connection_status.disconnected'),
            animate: false,
        },
    };
    return configs[connectionState.value] ?? configs.disconnected;
});

const handleClick = () => {
    if (!isConnected.value) {
        manualReconnect();
    }
};
</script>

<template>
    <div class="relative group">
        <button
            type="button"
            :class="[statusConfig.dot, statusConfig.animate ? 'animate-pulse' : '', !isConnected ? 'cursor-pointer hover:ring-2 hover:ring-offset-1 hover:ring-gray-300' : 'cursor-default']"
            class="h-2.5 w-2.5 rounded-full transition-all"
            :title="statusConfig.label"
            @click="handleClick"
        />
        <!-- Tooltip -->
        <div class="absolute end-0 top-full mt-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs rounded-md opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none shadow-lg z-50">
            <span class="font-medium">{{ statusConfig.label }}</span>
            <span v-if="connectionError" class="block text-red-300 text-[10px] mt-0.5">
                {{ connectionError }}
            </span>
            <span v-if="!isConnected && !statusConfig.animate" class="block text-gray-400 text-[10px] mt-0.5">
                {{ t('connection_status.click_to_reconnect') }}
            </span>
        </div>
    </div>
</template>
