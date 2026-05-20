<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import {
    HomeIcon,
    WrenchScrewdriverIcon,
    BanknotesIcon,
    ChartBarIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';

defineProps<{ vendorName?: string }>();

const { t } = useI18n();

// Literal hrefs (not route()) so the shared layout never couples to a
// portal route that a later page registers; all live under /v/portal.
const nav = [
    { href: '/v/portal', icon: HomeIcon, label: 'vendor_portal.nav.dashboard', testid: 'nav-dashboard' },
    { href: '/v/portal/jobs', icon: WrenchScrewdriverIcon, label: 'vendor_portal.nav.inbox', testid: 'nav-inbox' },
    { href: '/v/portal/statement', icon: BanknotesIcon, label: 'vendor_portal.nav.statement', testid: 'nav-statement' },
    { href: '/v/portal/sla', icon: ChartBarIcon, label: 'vendor_portal.nav.sla', testid: 'nav-sla' },
];

const logout = () => router.post('/v/portal/logout');
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white shadow-sm">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
                <div class="flex items-center gap-2">
                    <WrenchScrewdriverIcon class="h-6 w-6 text-indigo-600" />
                    <span class="font-semibold text-gray-900">{{ vendorName || 'Contractor portal' }}</span>
                </div>
                <button
                    type="button"
                    @click="logout"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800"
                    data-testid="vendor-logout"
                >
                    <ArrowRightOnRectangleIcon class="h-4 w-4" />
                    {{ t('vendor_portal.nav.logout') }}
                </button>
            </div>
        </header>

        <nav class="border-b bg-white">
            <div class="mx-auto flex max-w-5xl gap-1 px-4 sm:px-6">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    :data-testid="item.testid"
                    class="border-b-2 border-transparent px-3 py-3 text-sm font-medium text-gray-600 hover:border-indigo-300 hover:text-gray-900"
                >
                    <component :is="item.icon" class="me-1 inline h-4 w-4" />
                    {{ t(item.label) }}
                </Link>
            </div>
        </nav>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <slot />
        </main>
    </div>
</template>
