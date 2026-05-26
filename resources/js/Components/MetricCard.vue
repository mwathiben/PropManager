<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import {
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
} from '@heroicons/vue/24/solid';

type Format = 'currency' | 'number' | 'percent' | 'text';
type Color = 'emerald' | 'blue' | 'red' | 'yellow' | 'indigo' | 'gray' | 'purple' | 'orange';

interface Trend {
    direction: 'up' | 'down';
    value: string;
}

interface Props {
    title: string;
    value?: number | string;
    format?: Format;
    subtitle?: string;
    trend?: Trend | null;
    icon?: Component;
    color?: Color;
    href?: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    format: 'currency',
    trend: null,
    color: 'emerald',
    loading: false,
});

const { formatMoney, formatNumber, formatPercent } = useFormatters();
const { t } = useI18n();

const formattedValue = computed(() => {
    if (props.loading) return t('metric_card.loading_placeholder');
    if (props.value === null || props.value === undefined) return t('metric_card.empty_value');

    switch (props.format) {
        case 'currency':
            return formatMoney(props.value);
        case 'number':
            return formatNumber(props.value);
        case 'percent':
            return formatPercent(props.value);
        case 'text':
        default:
            return props.value;
    }
});

const colorClasses = computed(() => {
    const colors = {
        emerald: {
            bg: 'bg-emerald-50',
            icon: 'bg-emerald-100 text-emerald-600',
            text: 'text-emerald-600',
        },
        blue: {
            bg: 'bg-blue-50',
            icon: 'bg-blue-100 text-blue-600',
            text: 'text-blue-600',
        },
        red: {
            bg: 'bg-red-50',
            icon: 'bg-red-100 text-red-600',
            text: 'text-red-600',
        },
        yellow: {
            bg: 'bg-yellow-50',
            icon: 'bg-yellow-100 text-yellow-600',
            text: 'text-yellow-600',
        },
        indigo: {
            bg: 'bg-indigo-50',
            icon: 'bg-indigo-100 text-indigo-600',
            text: 'text-indigo-600',
        },
        gray: {
            bg: 'bg-gray-50',
            icon: 'bg-gray-100 text-gray-600',
            text: 'text-gray-600',
        },
        purple: {
            bg: 'bg-purple-50',
            icon: 'bg-purple-100 text-purple-600',
            text: 'text-purple-600',
        },
        orange: {
            bg: 'bg-orange-50',
            icon: 'bg-orange-100 text-orange-600',
            text: 'text-orange-600',
        },
    };
    return colors[props.color] || colors.emerald;
});

const trendClasses = computed(() => {
    if (!props.trend) return null;
    return props.trend.direction === 'up'
        ? 'text-emerald-600'
        : 'text-red-600';
});
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        :class="[
            'block bg-white rounded-xl border border-gray-200 p-5 transition-all duration-200', /* i18n-ignore */
            href ? 'hover:shadow-md hover:border-gray-300 cursor-pointer' : '', /* i18n-ignore */
        ]"
    >
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 truncate">{{ title }}</p>
                <p
                    :class="[
                        'mt-2 text-2xl font-semibold', /* i18n-ignore */
                        loading ? 'animate-pulse text-gray-400' : 'text-gray-900' /* i18n-ignore */
                    ]"
                >
                    {{ formattedValue }}
                </p>
                <div v-if="subtitle || trend" class="mt-1 flex items-center gap-2">
                    <p v-if="subtitle" class="text-xs text-gray-500">{{ subtitle }}</p>
                    <div v-if="trend" :class="['flex items-center gap-0.5 text-xs font-medium', trendClasses]">
                        <component
                            :is="trend.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                            class="h-3.5 w-3.5"
                        />
                        <span>{{ trend.value }}</span>
                    </div>
                </div>
            </div>
            <div v-if="icon" :class="['p-2.5 rounded-lg', colorClasses.icon]">
                <component :is="icon" class="h-5 w-5" />
            </div>
        </div>
    </component>
</template>
