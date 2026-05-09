<script setup lang="ts">
import {
    PlusCircleIcon,
    ArrowPathIcon,
    UserPlusIcon,
    ChatBubbleLeftIcon,
    CheckCircleIcon,
    LockClosedIcon,
    StarIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';
import { useFormatters } from '@/composables';
import type { TicketActivity } from '@/types';

const { formatDateTime } = useFormatters();

const props = defineProps<{
    activities: TicketActivity[];
}>();

const getIcon = (action) => {
    const icons = {
        'created': PlusCircleIcon,
        'status_changed': ArrowPathIcon,
        'assigned': UserPlusIcon,
        'commented': ChatBubbleLeftIcon,
        'resolved': CheckCircleIcon,
        'closed': LockClosedIcon,
        'feedback_submitted': StarIcon
    };
    return icons[action] || InformationCircleIcon;
};

const getColor = (action) => {
    const colors = {
        'created': 'text-blue-500 bg-blue-100',
        'status_changed': 'text-purple-500 bg-purple-100',
        'assigned': 'text-indigo-500 bg-indigo-100',
        'commented': 'text-gray-500 bg-gray-100',
        'resolved': 'text-green-500 bg-green-100',
        'closed': 'text-gray-500 bg-gray-100',
        'feedback_submitted': 'text-yellow-500 bg-yellow-100'
    };
    return colors[action] || 'text-gray-500 bg-gray-100';
};
</script>

<template>
    <div class="flow-root">
        <ul role="list" class="-mb-8">
            <li v-for="(activity, index) in activities" :key="activity.id">
                <div class="relative pb-8">
                    <span
                        v-if="index !== activities.length - 1"
                        class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200"
                        aria-hidden="true"
                    />
                    <div class="relative flex space-x-3">
                        <div>
                            <span :class="[getColor(activity.action), 'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white']">
                                <component :is="getIcon(activity.action)" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                            <div>
                                <p class="text-sm text-gray-900">
                                    {{ activity.description || activity.action }}
                                    <template v-if="activity.old_value && activity.new_value">
                                        <span class="text-gray-500">
                                            from <span class="font-medium">{{ activity.old_value }}</span>
                                            to <span class="font-medium">{{ activity.new_value }}</span>
                                        </span>
                                    </template>
                                </p>
                                <p v-if="activity.user" class="text-xs text-gray-500">
                                    by {{ activity.user.name }}
                                </p>
                            </div>
                            <div class="whitespace-nowrap text-right text-xs text-gray-500">
                                {{ formatDateTime(activity.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
