<script setup lang="ts">
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" />
        <div class="relative bg-white rounded-lg shadow-xl sm:max-w-lg w-full m-4 p-6">
            <div class="flex flex-col items-center justify-center py-8">
                <svg
                    class="animate-spin h-10 w-10 text-emerald-600"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    />
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                </svg>
                <p class="mt-4 text-sm text-gray-500">Loading...</p>
            </div>
        </div>
    </div>
</template>
