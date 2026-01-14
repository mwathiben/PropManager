<script setup>
import { Link } from '@inertiajs/vue3';
import ArrowUpIcon from '@heroicons/vue/24/solid/ArrowUpIcon';
import ArrowDownIcon from '@heroicons/vue/24/solid/ArrowDownIcon';

defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [String, Number],
        required: true
    },
    subtitle: {
        type: String,
        default: ''
    },
    icon: {
        type: [Object, Function],
        default: null
    },
    iconBgColor: {
        type: String,
        default: 'bg-gray-100'
    },
    iconColor: {
        type: String,
        default: 'text-gray-600'
    },
    trend: {
        type: Object,
        default: null
        // { direction: 'up' | 'down', value: '5%', label: 'from last month' }
    },
    href: {
        type: String,
        default: ''
    }
});
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href || undefined"
        :class="[
            'bg-white rounded-xl p-6 shadow-sm border border-gray-100 transition-all duration-200',
            href ? 'hover:shadow-md hover:border-gray-200 cursor-pointer' : ''
        ]"
    >
        <div class="flex items-start justify-between mb-4">
            <!-- Icon -->
            <div
                v-if="icon"
                :class="[iconBgColor, 'h-10 w-10 rounded-full flex items-center justify-center']"
            >
                <component :is="icon" :class="[iconColor, 'h-5 w-5']" />
            </div>

            <!-- Trend Badge -->
            <div
                v-if="trend"
                :class="[
                    'flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full',
                    trend.direction === 'up' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
                ]"
            >
                <ArrowUpIcon v-if="trend.direction === 'up'" class="h-3 w-3" />
                <ArrowDownIcon v-else class="h-3 w-3" />
                {{ trend.value }}
            </div>
        </div>

        <!-- Value -->
        <div class="text-2xl font-bold text-gray-900">
            {{ value }}
        </div>

        <!-- Title -->
        <div class="text-sm text-gray-500 mt-1">
            {{ title }}
        </div>

        <!-- Subtitle / Trend Label -->
        <div v-if="subtitle || (trend && trend.label)" class="text-xs text-gray-400 mt-2">
            {{ subtitle || trend.label }}
        </div>
    </component>
</template>
