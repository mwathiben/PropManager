<script setup>
const props = defineProps({
    priority: {
        type: String,
        required: true
    }
});

const priorityConfig = {
    low: { label: 'Low', class: 'bg-gray-100 text-gray-700' },
    medium: { label: 'Medium', class: 'bg-blue-100 text-blue-700' },
    high: { label: 'High', class: 'bg-orange-100 text-orange-700' },
    urgent: { label: 'Urgent', class: 'bg-red-100 text-red-700' }
};

const config = priorityConfig[props.priority] || { label: props.priority, class: 'bg-gray-100 text-gray-700' };
</script>

<template>
    <span :class="[config.class, 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium']">
        {{ config.label }}
    </span>
</template>
