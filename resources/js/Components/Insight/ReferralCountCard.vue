<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    count: number;
}>();
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $t('insight.landlord_growth.referrals_card_heading') }}</p>
        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ count }}</p>
        <Link href="/referrals/mine" class="mt-1 inline-block text-sm text-indigo-700 hover:underline">
            {{ $t('insight.landlord_growth.deep_dive_link') }}
        </Link>
    </div>
</template>
