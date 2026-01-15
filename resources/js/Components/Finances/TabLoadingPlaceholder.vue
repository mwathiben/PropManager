<script setup lang="ts">
</script>

<template>
    <div class="space-y-6 animate-pulse">
        <!-- Stats row skeleton -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="h-4 w-24 bg-gray-200 rounded mb-3" />
                <div class="h-8 w-32 bg-gray-200 rounded mb-2" />
                <div class="h-3 w-20 bg-gray-200 rounded" />
            </div>
        </div>

        <!-- Filter bar skeleton -->
        <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-200 p-4">
            <div class="h-10 w-64 bg-gray-200 rounded-lg" />
            <div class="h-10 w-40 bg-gray-200 rounded-lg" />
            <div class="h-10 w-32 bg-gray-200 rounded-lg" />
            <div class="ml-auto h-10 w-24 bg-gray-200 rounded-lg" />
        </div>

        <!-- Table skeleton -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <!-- Table header -->
            <div class="bg-gray-50 border-b border-gray-200 px-4 py-3 flex gap-4">
                <div class="h-4 w-32 bg-gray-200 rounded" />
                <div class="h-4 w-24 bg-gray-200 rounded" />
                <div class="h-4 w-20 bg-gray-200 rounded" />
                <div class="h-4 w-28 bg-gray-200 rounded" />
                <div class="h-4 w-20 bg-gray-200 rounded" />
                <div class="h-4 w-24 bg-gray-200 rounded" />
            </div>
            <!-- Table rows -->
            <div v-for="i in 8" :key="i" class="px-4 py-4 border-b border-gray-100 flex gap-4 items-center">
                <div class="h-4 w-28 bg-gray-200 rounded" />
                <div class="h-4 w-32 bg-gray-200 rounded" />
                <div class="h-4 w-16 bg-gray-200 rounded" />
                <div class="h-4 w-24 bg-gray-200 rounded" />
                <div class="h-6 w-16 bg-gray-200 rounded-full" />
                <div class="h-4 w-20 bg-gray-200 rounded" />
            </div>
        </div>
    </div>
</template>
