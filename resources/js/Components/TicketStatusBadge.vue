<script setup>
const props = defineProps({
    status: {
        type: String,
        required: true
    }
});

const statusConfig = {
    open: { label: 'Open', class: 'bg-yellow-100 text-yellow-800' },
    acknowledged: { label: 'Acknowledged', class: 'bg-blue-100 text-blue-800' },
    in_progress: { label: 'In Progress', class: 'bg-purple-100 text-purple-800' },
    resolved: { label: 'Resolved', class: 'bg-green-100 text-green-800' },
    closed: { label: 'Closed', class: 'bg-gray-100 text-gray-800' },
    cancelled: { label: 'Cancelled', class: 'bg-red-100 text-red-800' }
};

const config = statusConfig[props.status] || { label: props.status, class: 'bg-gray-100 text-gray-800' };
</script>

<template>
    <span :class="[config.class, 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium']">
        {{ config.label }}
    </span>
</template>
