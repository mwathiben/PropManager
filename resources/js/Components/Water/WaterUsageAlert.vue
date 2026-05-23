<script setup lang="ts">
/**
 * Phase-93: shared high-usage / leak self-alert. Surfaces the Phase-86 is_anomalous
 * spike flag of the account's latest reading as a non-alarming advisory. Reusable
 * by the Phase-94+ water-client view (same anomaly source).
 */
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

interface Alert { consumption: number; reading_date: string | null }

const { t } = useI18n();
const { formatNumber, formatDate } = useFormatters();

defineProps<{ alert?: Alert | null }>();
</script>

<template>
    <div v-if="alert" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4" data-testid="water-usage-alert">
        <div class="flex items-start gap-3">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
            <div>
                <h3 class="font-semibold text-amber-900">{{ t('water.account.alert_title') }}</h3>
                <p class="mt-1 text-sm text-amber-700">{{ t('water.account.alert_body', { units: formatNumber(alert.consumption) }) }}</p>
                <p v-if="alert.reading_date" class="mt-1 text-xs text-amber-600">{{ t('water.account.alert_date') }}: {{ formatDate(alert.reading_date) }}</p>
            </div>
        </div>
    </div>
</template>
