<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { useAnnouncer } from '@/composables/useAnnouncer';
import { useI18n } from '@/composables/useI18n';
import { useFocusTrap } from '@/composables/useFocusTrap';
import { useEscapeKey } from '@/composables/useEscapeKey';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LiveAnnouncer from '@/Components/LiveAnnouncer.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import InboxBell from '@/Components/InboxBell.vue';
import PropertySwitcher from '@/Components/PropertySwitcher.vue';
import ConflictDialog from '@/Components/Offline/ConflictDialog.vue';
import { on as onWriteConflict } from '@/lib/writeConflictBus';
import ConnectionStatus from '@/Components/ConnectionStatus.vue';
import OnlineIndicator from '@/Components/OnlineIndicator.vue';
import QueuedOpsTray from '@/Components/QueuedOpsTray.vue';
import SlowNetworkBanner from '@/Components/Layout/SlowNetworkBanner.vue';
import NpsSurveyModal from '@/Components/Nps/NpsSurveyModal.vue';
import TourOverlay from '@/Components/Tour/TourOverlay.vue';
import InvitationBanner from '@/Components/InvitationBanner.vue';
import {
    HomeIcon,
    BuildingOffice2Icon,
    DocumentTextIcon,
    TicketIcon,
    FolderIcon,
    UserGroupIcon,
    Cog6ToothIcon,
    WrenchScrewdriverIcon,
    ChartBarIcon,
    BellIcon,
    ArrowUpTrayIcon,
    ClipboardDocumentListIcon,
    CreditCardIcon,
    KeyIcon,
    ExclamationTriangleIcon,
    Bars3Icon,
    XMarkIcon,
    ChevronDownIcon,
    ArrowRightStartOnRectangleIcon,
    UserCircleIcon,
    HomeModernIcon,
    QuestionMarkCircleIcon,
    SparklesIcon,
    UsersIcon,
    UserPlusIcon,
    PlusCircleIcon,
    ClipboardDocumentCheckIcon,
    ArrowRightOnRectangleIcon,
    BanknotesIcon,
    BeakerIcon,
    WalletIcon,
    ClockIcon,
    DocumentDuplicateIcon,
    ArchiveBoxIcon,
    EnvelopeIcon,
    ShieldCheckIcon,
    ChatBubbleLeftRightIcon,
    CurrencyDollarIcon,
    ArrowUturnLeftIcon,
    ScaleIcon,
} from '@heroicons/vue/24/outline';

defineSlots<{
    header(): unknown;
    default(): unknown;
}>();

const showMobileSidebar = ref(false);

// Phase-64 OFFLINE-MOUNTS-1: global ConflictDialog wired to
// writeConflictBus. Any 409 from the offline-replay loop surfaces a
// modal with overwrite / discard / merge options.
const conflictDialogOpen = ref(false);
const conflictPayload = ref<any>(null);
let unsubscribeWriteConflict: (() => void) | null = null;

function onConflictResolve(resolution: 'overwrite' | 'discard' | 'merge'): void {
    // For now both branches close the dialog. A follow-up cycle wires
    // overwrite -> re-POST with the now-current version, and merge ->
    // per-field selection.
    conflictDialogOpen.value = false;
    conflictPayload.value = null;
}

onMounted(() => {
    unsubscribeWriteConflict = onWriteConflict((payload) => {
        conflictPayload.value = {
            current_version: (payload.current as any)?.version ?? 0,
            current: payload.current ?? {},
            incoming: payload.incoming ?? {},
            diff: payload.diff ?? {},
        };
        conflictDialogOpen.value = true;
    });
});

onBeforeUnmount(() => {
    unsubscribeWriteConflict?.();
});

// Phase-23 A11Y-KBD-3: the mobile sidebar is a full-screen overlay
// over the page — functionally a modal — so it gets the same
// treatment as Modal.vue: a focus trap, Escape-to-close, body
// scroll lock, and focus restored to the hamburger on close.
const mobileSidebarRef = ref(null);
const hamburgerRef = ref(null);

function closeMobileSidebar() {
    showMobileSidebar.value = false;
    requestAnimationFrame(() => hamburgerRef.value?.focus());
}

useFocusTrap(mobileSidebarRef, showMobileSidebar);
useEscapeKey(() => {
    if (showMobileSidebar.value) {
        closeMobileSidebar();
    }
}, showMobileSidebar);
useBodyScrollLock(showMobileSidebar);

const page = usePage();
const { t } = useI18n();
const { user, isSuperAdmin, isLandlord, isCaretaker, isTenant, isRestricted, can } = useAuth();
const isImpersonating = computed(() => page.props.impersonating || false);
const navBadges = computed(() => page.props.navBadges || {});
const featureAccess = computed(() => page.props.featureAccess || {});

const pendingInvitations = computed(() => page.props.pendingInvitations || []);

// Phase-23 A11Y-SR-1: read Inertia flash messages into the live
// announcer — success/info politely, errors assertively — so a
// screen-reader user hears post-redirect status without a focus move.
const { announce } = useAnnouncer();
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) {
            return;
        }
        if (flash.success) {
            announce(flash.success, 'polite');
        }
        if (flash.message) {
            announce(flash.message, 'polite');
        }
        if (flash.error) {
            announce(flash.error, 'assertive');
        }
    },
    { immediate: true, deep: true },
);

const stopImpersonating = () => {
    router.post(route('admin.stopImpersonating'));
};

// Role display configuration. Labels resolve through vue-i18n so the
// role badge tracks the active locale (Phase-24 I18N-FRONT-3).
const roleConfig = computed(() => {
    const configs = {
        'super_admin': { label: t('role.system_admin'), color: 'bg-purple-600', icon: KeyIcon },
        'landlord': { label: t('role.landlord'), color: 'bg-blue-600', icon: BuildingOffice2Icon },
        'caretaker': { label: t('role.caretaker'), color: 'bg-green-600', icon: WrenchScrewdriverIcon },
        'tenant': { label: t('role.tenant'), color: 'bg-amber-600', icon: HomeIcon },
        'water_client': { label: t('role.water_client'), color: 'bg-cyan-600', icon: BeakerIcon },
        'owner': { label: t('role.owner'), color: 'bg-indigo-600', icon: BuildingOffice2Icon },
    };
    return configs[user.value?.role] || { label: t('role.user'), color: 'bg-gray-600', icon: UserCircleIcon };
});

// Navigation items based on role.
// Phase-20 AUTHZ-FRONT-3: admin section gated via can('access-admin')
// instead of raw role-string. Phase-13 DPA-4 restriction propagates
// through Gate::before — a restricted super-admin's abilities map
// will have access-admin=true (it's on the read-side allow-list)
// so they still see the nav; per-action buttons in admin pages are
// individually gated via finer abilities.
const navigationItems = computed(() => {
    if (can('access-admin')) {
        return [
            { name: t('nav.dashboard'), href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard') },
            { name: t('nav.landlords'), href: route('admin.landlords'), icon: BuildingOffice2Icon, active: route().current('admin.landlords*') },
            { name: t('nav.all_users'), href: route('admin.users'), icon: UserGroupIcon, active: route().current('admin.users*') },
            { name: t('nav.platform_billing'), href: route('admin.billing.index'), icon: CurrencyDollarIcon, active: route().current('admin.billing*') },
            // Phase-79 NAV-REACH-2: wire the previously-orphaned onboarding funnel.
            { name: t('nav.onboarding_funnel'), href: route('ops.onboarding.funnel'), icon: ChartBarIcon, active: route().current('ops.onboarding.*') },
            { name: t('nav.system_settings'), href: route('admin.settings'), icon: Cog6ToothIcon, active: route().current('admin.settings') },
        ];
    }

    const role = user.value?.role;
    // A manager runs properties on owners' behalf with landlord-equal access
    // (isScopeOwner, Phase-1b), so it shares the operational nav and adds its
    // own Management section. Without this branch a manager fell through to the
    // empty default below.
    if (role === 'landlord' || role === 'manager') {
        return [
            { name: t('nav.dashboard'), href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard'), tour: 'nav-dashboard' },

            // PROPERTIES
            { type: 'divider', label: t('nav.properties_section') },
            { name: t('nav.portfolio'), href: route('properties.index'), icon: BuildingOffice2Icon, active: route().current('properties.index') || route().current('properties.show') || route().current('properties.current'), tour: 'nav-properties' },
            { name: t('nav.buildings'), href: route('buildings.index'), icon: HomeModernIcon, active: route().current('buildings.*'), tour: 'nav-buildings' },
            { name: t('nav.benchmark'), href: route('properties.benchmark'), icon: ChartBarIcon, active: route().current('properties.benchmark') },
            { name: t('nav.add_property'), href: route('onboarding.create'), icon: PlusCircleIcon, active: route().current('onboarding.*') },

            // TENANTS HUB (Consolidated)
            {
                name: t('nav.tenants'),
                href: route('tenants.hub'),
                icon: UsersIcon,
                active: route().current('tenants.*') || route().current('tenant-invitations.*') || route().current('verifications.*') || route().current('payment-verifications.*') || route().current('move-outs.*'),
                badgeKey: 'tenants',
                badgeColor: 'bg-blue-500',
                tour: 'nav-tenants'
            },

            // FINANCES HUB (Already consolidated)
            { name: t('nav.finances'), href: route('finances.index'), icon: BanknotesIcon, active: route().current('finances.*'), badgeKey: 'invoices', badgeColor: 'bg-red-500', tour: 'nav-finances' },
            { name: t('nav.payments'), href: route('payments-hub.overview'), icon: CreditCardIcon, active: route().current('payments-hub.*') },

            // MANAGEMENT (manager-only: agreements + the owners they act for)
            ...(role === 'manager' ? [
                { type: 'divider', label: t('nav.management_section') },
                { name: t('nav.agreements'), href: route('agreements.index'), icon: DocumentTextIcon, active: route().current('agreements.*') },
                { name: t('nav.owners'), href: route('owners.index'), icon: UsersIcon, active: route().current('owners.*') },
            ] : []),

            // MAINTENANCE HUB (Consolidated)
            {
                name: t('nav.maintenance'),
                href: route('maintenance.hub'),
                icon: WrenchScrewdriverIcon,
                active: route().current('maintenance.*') || route().current('tickets.*') || route().current('complaints.*'),
                badgeKey: 'tickets',
                badgeColor: 'bg-orange-500'
            },

            // WATER HUB (Conditional, Consolidated)
            ...(featureAccess.value.water_billing ? [{
                name: t('nav.water'),
                href: route('water.hub'),
                icon: BeakerIcon,
                active: route().current('water.*') || route().current('readings.*'),
                badgeKey: 'readings',
                badgeColor: 'bg-cyan-500'
            }] : []),

            // ARCHIVE HUB (Consolidated)
            {
                name: t('nav.archive'),
                href: route('archive.hub'),
                icon: ArchiveBoxIcon,
                active: route().current('archive.*') || route().current('documents.*') || route().current('leases.index') || route().current('activity-logs.*')
            },

            // OPERATIONS HUB (Consolidated)
            {
                name: t('nav.operations'),
                href: route('operations.hub'),
                icon: Cog6ToothIcon,
                active: route().current('operations.*') || route().current('notifications.*') || route().current('bulk.*') || route().current('invitations.*') || route().current('imports.*') || route().current('inbox.*'),
                badgeKey: 'inbox',
                badgeColor: 'bg-blue-500'
            },

            // Phase-64 INBOX-MOUNT-3: landlord-side message-thread entry.
            { name: t('nav.messages'), href: route('message-threads.index'), icon: EnvelopeIcon, active: route().current('message-threads.*'), badgeKey: 'inboxUnread', badgeColor: 'bg-indigo-500' },

            // Phase-65 HOLD-UI-3: landlord-side legal-hold compliance entry.
            { name: t('nav.legal_holds'), href: route('legal-holds.index'), icon: ScaleIcon, active: route().current('legal-holds.*'), badgeKey: 'legalHoldsActive', badgeColor: 'bg-rose-500' },

            // SETTINGS
            { type: 'divider', label: '' },
            { name: t('nav.settings'), href: route('settings.index'), icon: Cog6ToothIcon, active: route().current('settings.*') },
        ];
    }

    if (role === 'caretaker') {
        return [
            { name: t('nav.dashboard'), href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard'), tour: 'nav-dashboard' },
            // Phase-80 TASK-BOARD: mobile-first daily task board.
            { name: t('nav.my_tasks'), href: route('tasks.index'), icon: ClipboardDocumentListIcon, active: route().current('tasks.*') },
            { name: t('nav.my_tickets'), href: route('tickets.index'), icon: TicketIcon, active: route().current('tickets.*'), badgeKey: 'tickets', badgeColor: 'bg-yellow-500', tour: 'nav-tickets' },
            // Phase-79 WATER-RENAME-2: caretaker lands on the hub (record-readings tab), not the legacy standalone page.
            ...(featureAccess.value.water_billing ? [{ name: t('nav.water'), href: route('water.hub'), icon: BeakerIcon, active: route().current('water.*') || route().current('readings.*'), badgeKey: 'readings', badgeColor: 'bg-cyan-500' }] : []),
        ];
    }

    if (role === 'tenant') {
        return [
            { name: t('nav.dashboard'), href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard'), tour: 'nav-dashboard' },
            { name: t('nav.my_finances'), href: route('tenant.finances.index'), icon: BanknotesIcon, active: route().current('tenant.finances.*'), badgeKey: 'invoices', badgeColor: 'bg-red-500', tour: 'nav-tenant-finances' },
            // Phase-79 NAV-REACH-2: wire the previously-orphaned tenant wallet.
            { name: t('nav.my_wallet'), href: route('tenant.wallet.index'), icon: WalletIcon, active: route().current('tenant.wallet.*') },
            // Phase-84 PAY-METHODS: tenant saved payment methods.
            { name: t('nav.payment_methods'), href: route('tenant.payment-methods.index'), icon: CreditCardIcon, active: route().current('tenant.payment-methods.*') },
            { name: t('nav.my_tickets'), href: route('tickets.index'), icon: TicketIcon, active: route().current('tickets.*'), badgeKey: 'tickets', badgeColor: 'bg-yellow-500' },
            { name: t('nav.my_lease'), href: route('tenant.lease'), icon: DocumentTextIcon, active: route().current('tenant.lease') },
            // Phase-79 WATER-GATE-4: tenant water view, only when the landlord charges for water.
            ...(featureAccess.value.water_billing ? [{ name: t('nav.my_water'), href: route('tenant.water'), icon: BeakerIcon, active: route().current('tenant.water') }] : []),
            { name: t('nav.notifications'), href: route('tenant.notifications'), icon: BellIcon, active: route().current('tenant.notifications'), badgeKey: 'notifications', badgeColor: 'bg-indigo-500' },
            // Phase-64 INBOX-MOUNT-3: tenant-side inbox entry.
            { name: t('nav.inbox'), href: route('tenant.inbox.index'), icon: EnvelopeIcon, active: route().current('tenant.inbox.*'), badgeKey: 'inboxUnread', badgeColor: 'bg-indigo-500', tour: 'nav-inbox' },
        ];
    }

    // Phase-95 WATER-CLIENT: a water-only account — their dashboard is their water
    // view (Phase 96 enriches it). Profile/logout live in the shared user menu.
    if (role === 'water_client') {
        return [
            { name: t('nav.dashboard'), href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard'), tour: 'nav-dashboard' },
            { name: t('nav.my_water_charges'), href: route('water-client.finances'), icon: BanknotesIcon, active: route().current('water-client.finances') },
        ];
    }

    // Phase-102 OWNER-PORTAL: a property owner sees their properties + statements.
    if (role === 'owner') {
        return [
            { name: t('owners.portal.dashboard_title'), href: route('owner-portal.dashboard'), icon: BuildingOffice2Icon, active: route().current('owner-portal.dashboard') },
            { name: t('owners.portal.statements_title'), href: route('owner-portal.statements'), icon: DocumentTextIcon, active: route().current('owner-portal.statements') },
            { name: t('owners.portal.payouts_title'), href: route('owner-portal.payouts'), icon: BanknotesIcon, active: route().current('owner-portal.payouts') },
            { name: t('owners.portal.notifications_title'), href: route('owner-portal.notifications'), icon: BellIcon, active: route().current('owner-portal.notifications'), badgeKey: 'notifications', badgeColor: 'bg-indigo-500' },
        ];
    }

    return [];
});
</script>

<template>
    <div>
        <!--
            Phase-23 A11Y-SR-1: the live-region pair, mounted once for
            the whole authenticated app.
        -->
        <LiveAnnouncer />

        <div class="min-h-screen bg-gray-100">
            <!--
                Phase-23 A11Y-KBD-1: skip-link (WCAG 2.4.1 Bypass
                Blocks). First focusable element on every page — lets a
                keyboard user jump past the sidebar nav straight to the
                page content. Visually hidden until focused.
            -->
            <a
                href="#main-content"
                class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-[100] focus:rounded-md focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                {{ t('nav.skip_to_main') }}
            </a>

            <!--
                Impersonation Banner.
                Phase-20 AUTHZ-FRONT-9: when the impersonated target is
                DPA-4 restricted, append a read-only suffix so the
                operator sees that write-side actions will be denied
                even though they're acting as super-admin. Pre-Phase-20
                a super-admin impersonating a restricted user saw the
                full write UI; the server denies, but the banner gave
                no warning.
            -->
            <div v-if="isImpersonating" class="bg-yellow-500 text-yellow-900 px-4 py-2 fixed top-0 start-0 end-0 z-50">
                <div class="max-w-7xl mx-auto flex items-center justify-between">
                    <div class="flex items-center">
                        <ExclamationTriangleIcon class="h-5 w-5 me-2" />
                        <span class="font-medium">
                            {{ t('banner.viewing_as') }} <strong>{{ user.name }}</strong> ({{ user.role }})
                            <span v-if="isRestricted" class="ms-2 text-red-900 font-bold">
                                {{ t('banner.read_only_article_18') }}
                            </span>
                        </span>
                    </div>
                    <button @click="stopImpersonating"
                            class="px-3 py-1 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm font-medium">
                        {{ t('banner.stop_impersonating') }}
                    </button>
                </div>
            </div>

            <!-- SIDEBAR (Desktop) -->
            <aside class="fixed inset-y-0 start-0 z-40 w-64 bg-white border-r border-gray-200 hidden lg:flex lg:flex-col"
                   :class="{ 'top-10': isImpersonating }">

                <!-- Logo & Role Badge -->
                <div class="h-16 flex items-center justify-between px-4 border-b border-gray-200">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <ApplicationLogo class="h-8 w-auto fill-current text-gray-800" aria-hidden="true" />
                        <span class="sr-only">{{ t('brand.go_to_dashboard') }}</span>
                    </Link>
                </div>

                <!-- Role Indicator -->
                <div class="px-4 py-3 border-b border-gray-100">
                    <Link
                        v-if="user.role === 'landlord'"
                        :href="route('buildings.index')"
                        class="flex items-center gap-3 p-2 -m-2 rounded-lg hover:bg-indigo-50 transition-colors group"
                    >
                        <div :class="roleConfig.color" class="h-10 w-10 rounded-lg flex items-center justify-center text-white shadow-sm group-hover:ring-2 group-hover:ring-indigo-300 group-hover:ring-offset-1 transition-all">
                            <component :is="roleConfig.icon" class="h-5 w-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate group-hover:text-indigo-700 transition-colors">{{ user.name }}</p>
                            <p class="text-xs text-gray-500 group-hover:text-indigo-500 transition-colors">{{ t('nav.view_buildings') }}</p>
                        </div>
                    </Link>
                    <div v-else class="flex items-center gap-3">
                        <div :class="roleConfig.color" class="h-10 w-10 rounded-lg flex items-center justify-center text-white shadow-sm">
                            <component :is="roleConfig.icon" class="h-5 w-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ user.name }}</p>
                            <p class="text-xs text-gray-500">{{ roleConfig.label }}</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <!--
                    Phase-23 A11Y-SR-2: distinct aria-label so a screen
                    reader's landmark list can tell the multiple <nav>s
                    apart (desktop sidebar / mobile sidebar / breadcrumb).
                -->
                <nav :aria-label="t('nav.primary_label')" class="flex-1 overflow-y-auto py-4 px-3">
                    <template v-for="(item, index) in navigationItems" :key="index">
                        <!-- Divider -->
                        <div v-if="item.type === 'divider'" class="mt-6 mb-2">
                            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ item.label }}</p>
                        </div>

                        <!-- Nav Link i18n-ignore -->
                        <Link v-else
                              :href="item.href"
                              :data-tour="item.tour"
                              :class="[
                                  item.active
                                      ? 'bg-indigo-50 text-indigo-700 font-semibold'
                                      : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                              ]"
                              class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors mb-1">
                            <component :is="item.icon"
                                       :class="item.active ? 'text-indigo-600' : 'text-gray-400'"
                                       class="h-5 w-5 shrink-0" />
                            <span class="flex-1">{{ item.name }}</span>
                            <!-- Badge -->
                            <span v-if="item.badgeKey && navBadges[item.badgeKey] > 0"
                                  :class="item.badgeColor"
                                  class="ms-auto min-w-5 h-5 px-1.5 rounded-full text-xs font-bold text-white flex items-center justify-center">
                                {{ navBadges[item.badgeKey] > 99 ? '99+' : navBadges[item.badgeKey] }}
                            </span>
                        </Link>
                    </template>
                </nav>

                <!-- User Menu (Bottom) -->
                <div class="border-t border-gray-200 p-4">
                    <Dropdown align="left" width="56" :dropUp="true">
                        <template #trigger>
                            <button class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors text-start group">
                                <div class="h-9 w-9 rounded-full bg-linear-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-medium text-sm shadow-sm">
                                    {{ user.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
                                </div>
                                <ChevronDownIcon class="h-4 w-4 text-gray-400 group-hover:text-gray-600 transition-colors" />
                            </button>
                        </template>
                        <template #content>
                            <!-- Account Section -->
                            <div class="px-4 py-2">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('menu.account') }}</p>
                            </div>
                            <DropdownLink :href="route('settings.index')">
                                <Cog6ToothIcon class="h-5 w-5 text-gray-400" />
                                <span>{{ t('menu.settings') }}</span>
                            </DropdownLink>
                            <DropdownLink :href="route('profile.edit')">
                                <UserCircleIcon class="h-5 w-5 text-gray-400" />
                                <span>{{ t('menu.profile') }}</span>
                            </DropdownLink>

                            <!-- Support Section -->
                            <div class="my-1 mx-2 border-t border-gray-100"></div>
                            <div class="px-4 py-2">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('menu.support') }}</p>
                            </div>
                            <DropdownLink :href="route('help.index')">
                                <QuestionMarkCircleIcon class="h-5 w-5 text-gray-400" />
                                <span>{{ t('menu.get_help') }}</span>
                            </DropdownLink>

                            <!-- Billing Section (Landlords only) -->
                            <template v-if="user.role === 'landlord'">
                                <div class="my-1 mx-2 border-t border-gray-100"></div>
                                <div class="px-4 py-2">
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('menu.billing') }}</p>
                                </div>
                                <DropdownLink :href="route('finances.settings')">
                                    <BanknotesIcon class="h-5 w-5 text-gray-400" />
                                    <span>{{ t('menu.payment_settings') }}</span>
                                </DropdownLink>
                                <DropdownLink :href="route('invoice-settings.edit')">
                                    <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                                    <span>{{ t('menu.invoice_settings') }}</span>
                                </DropdownLink>
                                <DropdownLink :href="route('invoice-templates.index')">
                                    <DocumentDuplicateIcon class="h-5 w-5 text-gray-400" />
                                    <span>{{ t('menu.invoice_templates') }}</span>
                                </DropdownLink>
                                <DropdownLink :href="route('subscription.index')">
                                    <SparklesIcon class="h-5 w-5 text-gray-400" />
                                    <span>{{ t('menu.subscription') }}</span>
                                </DropdownLink>
                            </template>

                            <!-- Log Out -->
                            <div class="my-1 mx-2 border-t border-gray-100"></div>
                            <DropdownLink :href="route('logout')" method="post" as="button" :danger="true">
                                <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                                <span>{{ t('menu.log_out') }}</span>
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </aside>

            <!-- MOBILE SIDEBAR -->
            <div v-if="showMobileSidebar" class="fixed inset-0 z-50 lg:hidden">
                <div class="fixed inset-0 bg-gray-900/50" @click="closeMobileSidebar"></div>
                <aside
                    ref="mobileSidebarRef"
                    id="mobile-sidebar"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="t('nav.navigation_menu')"
                    class="fixed inset-y-0 start-0 w-64 bg-white shadow-xl flex flex-col"
                >
                    <div class="h-16 flex items-center justify-between px-4 border-b border-gray-200">
                        <Link :href="route('dashboard')" class="flex items-center">
                            <ApplicationLogo class="h-8 w-auto fill-current text-gray-800" />
                        </Link>
                        <button @click="closeMobileSidebar" :aria-label="t('nav.close_menu')" class="p-2 rounded-md hover:bg-gray-100">
                            <XMarkIcon class="h-5 w-5 text-gray-500" aria-hidden="true" />
                        </button>
                    </div>

                    <!-- Role Indicator (Mobile) -->
                    <div class="px-4 py-3 border-b border-gray-100">
                        <Link
                            v-if="user.role === 'landlord'"
                            :href="route('buildings.index')"
                            @click="showMobileSidebar = false"
                            class="flex items-center gap-3 p-2 -m-2 rounded-lg hover:bg-indigo-50 transition-colors group"
                        >
                            <div :class="roleConfig.color" class="h-10 w-10 rounded-lg flex items-center justify-center text-white shadow-sm">
                                <component :is="roleConfig.icon" class="h-5 w-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate group-hover:text-indigo-700">{{ user.name }}</p>
                                <p class="text-xs text-gray-500 group-hover:text-indigo-500">{{ t('nav.view_buildings') }}</p>
                            </div>
                        </Link>
                        <div v-else class="flex items-center gap-3">
                            <div :class="roleConfig.color" class="h-10 w-10 rounded-lg flex items-center justify-center text-white shadow-sm">
                                <component :is="roleConfig.icon" class="h-5 w-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ user.name }}</p>
                                <p class="text-xs text-gray-500">{{ roleConfig.label }}</p>
                            </div>
                        </div>
                    </div>

                    <nav :aria-label="t('nav.mobile_primary_label')" class="flex-1 overflow-y-auto py-4 px-3">
                        <template v-for="(item, index) in navigationItems" :key="index">
                            <div v-if="item.type === 'divider'" class="mt-6 mb-2">
                                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ item.label }}</p>
                            </div>
                            <!-- i18n-ignore -->
                            <Link v-else
                                  :href="item.href"
                                  :data-tour="item.tour"
                                  @click="showMobileSidebar = false"
                                  :class="[
                                      item.active
                                          ? 'bg-indigo-50 text-indigo-700 font-semibold'
                                          : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                                  ]"
                                  class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors mb-1">
                                <component :is="item.icon"
                                           :class="item.active ? 'text-indigo-600' : 'text-gray-400'"
                                           class="h-5 w-5 shrink-0" />
                                <span class="flex-1">{{ item.name }}</span>
                                <!-- Badge (Mobile) -->
                                <span v-if="item.badgeKey && navBadges[item.badgeKey] > 0"
                                      :class="item.badgeColor"
                                      class="ms-auto min-w-5 h-5 px-1.5 rounded-full text-xs font-bold text-white flex items-center justify-center">
                                    {{ navBadges[item.badgeKey] > 99 ? '99+' : navBadges[item.badgeKey] }}
                                </span>
                            </Link>
                        </template>
                    </nav>

                    <div class="border-t border-gray-200 p-4 space-y-1">
                        <!-- Account -->
                        <p class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('menu.account') }}</p>
                        <Link :href="route('settings.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <Cog6ToothIcon class="h-5 w-5 text-gray-400" />
                            <span>{{ t('menu.settings') }}</span>
                        </Link>
                        <Link :href="route('profile.edit')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <UserCircleIcon class="h-5 w-5 text-gray-400" />
                            <span>{{ t('menu.profile') }}</span>
                        </Link>

                        <!-- Support -->
                        <div class="my-2 border-t border-gray-100"></div>
                        <p class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('menu.support') }}</p>
                        <Link :href="route('help.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <QuestionMarkCircleIcon class="h-5 w-5 text-gray-400" />
                            <span>{{ t('menu.get_help') }}</span>
                        </Link>

                        <!-- Billing (Landlords only) -->
                        <template v-if="user.role === 'landlord'">
                            <div class="my-2 border-t border-gray-100"></div>
                            <p class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('menu.billing') }}</p>
                            <Link :href="route('finances.settings')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <BanknotesIcon class="h-5 w-5 text-gray-400" />
                                <span>{{ t('menu.payment_settings') }}</span>
                            </Link>
                            <Link :href="route('invoice-settings.edit')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                                <span>{{ t('menu.invoice_settings') }}</span>
                            </Link>
                            <Link :href="route('invoice-templates.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <DocumentDuplicateIcon class="h-5 w-5 text-gray-400" />
                                <span>{{ t('menu.invoice_templates') }}</span>
                            </Link>
                            <Link :href="route('subscription.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <SparklesIcon class="h-5 w-5 text-gray-400" />
                                <span>{{ t('menu.subscription') }}</span>
                            </Link>
                        </template>

                        <!-- Log Out -->
                        <div class="my-2 border-t border-gray-100"></div>
                        <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-3 text-sm text-red-600 py-2.5 px-3 rounded-lg hover:bg-red-50 w-full transition-colors">
                            <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                            <span>{{ t('menu.log_out') }}</span>
                        </Link>
                    </div>
                </aside>
            </div>

            <!-- MAIN CONTENT AREA -->
            <div class="lg:ps-64" :class="{ 'pt-10': isImpersonating }">
                <!-- Top Bar (Mobile hamburger + Sub-navigation slot) -->
                <header class="sticky top-0 z-30 bg-white border-b border-gray-200 h-16 flex items-center px-4 lg:px-8"
                        :class="{ 'top-10': isImpersonating }">
                    <!-- Mobile Menu Button -->
                    <button
                        ref="hamburgerRef"
                        @click="showMobileSidebar = true"
                        :aria-label="t('nav.open_menu')"
                        aria-controls="mobile-sidebar"
                        :aria-expanded="showMobileSidebar"
                        class="lg:hidden p-2 -ml-2 me-2 rounded-md hover:bg-gray-100"
                    >
                        <Bars3Icon class="h-6 w-6 text-gray-500" aria-hidden="true" />
                    </button>

                    <!-- Page Header Slot (for contextual sub-navigation) -->
                    <div class="flex-1">
                        <slot name="header" />
                    </div>

                    <!-- Connection Status & Notification Bell -->
                    <div class="flex items-center gap-3">
                        <!-- Phase-26 PWA-OFFLINE-3: HTTP-layer offline pill.
                             Silent when online (the absence is the signal).
                             Complementary to ConnectionStatus which tracks
                             the WebSocket/Echo realtime channel. -->
                        <PropertySwitcher />
                        <OnlineIndicator />
                        <ConnectionStatus />
                        <NotificationBell />
                        <InboxBell />
                    </div>
                </header>

                <!--
                    Phase-20 AUTHZ-FRONT-4: DPA-4 restriction banner.
                    Renders when the current user has restricted_at set
                    (Phase-13 DPA-4 / Kenya DPA Section 26(d) Article 18
                    right to restriction of processing). Pre-Phase-20
                    a restricted user clicking 'Edit' got a silent 403
                    with no UI feedback; the banner now makes the state
                    visible and points at the release path.
                -->
                <div
                    v-if="isRestricted"
                    role="alert"
                    class="bg-amber-50 border-s-4 border-amber-500 px-4 py-3"
                >
                    <div class="flex items-start gap-3">
                        <ExclamationTriangleIcon class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" aria-hidden="true" />
                        <div class="flex-1 text-sm text-amber-900">
                            <p class="font-semibold">{{ t('banner.restricted_title') }}</p>
                            <p class="mt-0.5">
                                {{ t('banner.restricted_body') }}
                                <Link :href="route('gdpr.index')" class="underline font-medium hover:text-amber-700">
                                    {{ t('banner.manage_privacy') }}
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Invitation Banner (for pending invitations) -->
                <InvitationBanner
                    v-if="pendingInvitations.length > 0"
                    :invitations="pendingInvitations"
                />

                <!-- Page Content -->
                <!--
                    Phase-23 A11Y-KBD-1: skip-link target. tabindex="-1"
                    makes <main> programmatically focusable so the
                    skip-link can move focus here without making it a
                    Tab stop.
                -->
                <!-- Phase-62 CONNECTIVITY-UX-1: slow-network heads-up.
                     Renders nothing unless useConnection.isSlow flips
                     true; dismissable per-session for users on
                     persistently slow networks. -->
                <SlowNetworkBanner />
                <main id="main-content" tabindex="-1">
                    <slot />
                </main>
            </div>
        </div>

        <!-- Phase-26 PWA-NETWORK-3: tray for ops queued for offline replay.
             Renders nothing while the store is empty (Pinia + v-if). -->
        <QueuedOpsTray />

        <!-- Phase-64 OFFLINE-MOUNTS-1: global ConflictDialog wired via
             writeConflictBus. Surfaces a 409 from the offline-replay
             layer regardless of which page is active. -->
        <ConflictDialog
            :open="conflictDialogOpen"
            :payload="conflictPayload"
            @resolve="onConflictResolve"
        />

        <!-- Phase-66 NPS-SURVEY-3: globally-mounted NPS prompt. Shows
             only when the server's auth.nps_prompt payload is present. -->
        <NpsSurveyModal />

        <!-- Phase-66 ONBOARDING-TOUR-3: globally-mounted product tour.
             Self-gates on the server's auth.onboarding_tour payload. -->
        <TourOverlay />
    </div>
</template>
