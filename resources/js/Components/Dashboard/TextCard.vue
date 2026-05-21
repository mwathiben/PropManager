<script setup lang="ts">
defineProps<{ card: { body: string } }>();
</script>

<template>
    <div class="mt-2" data-testid="text-card">
        <p class="whitespace-pre-line text-sm text-gray-600">{{ card.body }}</p>
    </div>
</template>
