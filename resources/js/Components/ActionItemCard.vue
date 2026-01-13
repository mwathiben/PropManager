<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    urgency: {
        type: String,
        default: 'medium',
        validator: (value) => ['critical', 'high', 'medium', 'low'].includes(value)
    },
    icon: {
        type: [Object, Function],
        required: true
    },
    title: {
        type: String,
        required: true
    },
    count: {
        type: Number,
        default: 0
    },
    description: {
        type: String,
        default: ''
    },
    actionLabel: {
        type: String,
        default: 'View'
    },
    actionHref: {
        type: String,
        default: ''
    }
});

const urgencyStyles = {
    critical: {
        card: 'bg-red-50 border-red-200 hover:border-red-300',
        icon: 'bg-red-100 text-red-600',
        count: 'text-red-700',
        text: 'text-red-600'
    },
    high: {
        card: 'bg-orange-50 border-orange-200 hover:border-orange-300',
        icon: 'bg-orange-100 text-orange-600',
        count: 'text-orange-700',
        text: 'text-orange-600'
    },
    medium: {
        card: 'bg-yellow-50 border-yellow-200 hover:border-yellow-300',
        icon: 'bg-yellow-100 text-yellow-600',
        count: 'text-yellow-700',
        text: 'text-yellow-600'
    },
    low: {
        card: 'bg-blue-50 border-blue-200 hover:border-blue-300',
        icon: 'bg-blue-100 text-blue-600',
        count: 'text-blue-700',
        text: 'text-blue-600'
    }
};

const styles = computed(() => urgencyStyles[props.urgency]);
</script>

<template>
    <component
        :is="actionHref ? Link : 'div'"
        :href="actionHref || undefined"
        :class="[
            styles.card,
            'rounded-xl border p-4 transition-all duration-200 block',
            actionHref ? 'cursor-pointer hover:shadow-md' : ''
        ]"
    >
        <div class="flex items-start gap-3">
            <!-- Icon -->
            <div :class="[styles.icon, 'flex-shrink-0 p-2 rounded-lg']">
                <component :is="icon" class="h-5 w-5" />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-2">
                    <span :class="[styles.count, 'text-2xl font-bold']">
                        {{ count }}
                    </span>
                    <span class="text-sm font-medium text-gray-700 truncate">
                        {{ title }}
                    </span>
                </div>
                <p v-if="description" :class="[styles.text, 'text-xs mt-1']">
                    {{ description }}
                </p>
            </div>
        </div>
    </component>
</template>
