<script setup>
const props = defineProps({
    completed: {
        type: Boolean,
        default: false
    },
    completedAt: {
        type: String,
        default: null
    },
    showDate: {
        type: Boolean,
        default: false
    }
});

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <span
        :class="[
            completed
                ? 'bg-green-100 text-green-800'
                : 'bg-yellow-100 text-yellow-800',
            'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium'
        ]"
    >
        <svg v-if="completed" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <svg v-else class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        {{ completed ? 'KYC Complete' : 'KYC Incomplete' }}
        <span v-if="showDate && completed && completedAt" class="text-gray-500">
            ({{ formatDate(completedAt) }})
        </span>
    </span>
</template>
