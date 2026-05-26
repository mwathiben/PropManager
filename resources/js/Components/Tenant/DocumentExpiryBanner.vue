<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDaysIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

interface ExpiringDoc {
    id: number;
    title: string;
    document_type: string;
    expires_at: string;
    days_remaining: number;
}

const { t } = useI18n();

const docs = computed<ExpiringDoc[]>(() => {
    const props = usePage().props as { tenantExpiringDocs?: ExpiringDoc[] };
    return props.tenantExpiringDocs ?? [];
});
</script>

<template>
    <div
        v-if="docs.length > 0"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-start gap-3"
        role="alert"
    >
        <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
        <div class="flex-1">
            <p class="text-sm font-medium text-amber-900">
                {{ docs.length === 1 ? t('tenant_document_expiry_banner.heading_single') : t('tenant_document_expiry_banner.heading_plural', { count: docs.length }) }}
            </p>
            <ul class="mt-2 space-y-1">
                <li
                    v-for="doc in docs"
                    :key="doc.id"
                    class="text-xs text-amber-800 flex items-center gap-2"
                >
                    <CalendarDaysIcon class="w-3 h-3" />
                    <span>{{ doc.title }} ({{ doc.document_type }}) —
                        <span v-if="doc.days_remaining < 0" class="font-semibold">{{ t('tenant_document_expiry_banner.expired_days_ago', Math.abs(doc.days_remaining)) }}</span>
                        <span v-else-if="doc.days_remaining === 0" class="font-semibold">{{ t('tenant_document_expiry_banner.expires_today') }}</span>
                        <span v-else>{{ t('tenant_document_expiry_banner.expires_in_days', doc.days_remaining) }}</span>
                    </span>
                </li>
            </ul>
            <Link
                :href="route('tenant.documents.index')"
                class="mt-2 inline-block text-xs font-medium text-amber-900 underline hover:text-amber-700"
            >
                {{ t('tenant_document_expiry_banner.review_documents') }}
            </Link>
        </div>
    </div>
</template>
