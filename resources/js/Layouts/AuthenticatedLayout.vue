<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { useAnnouncer } from '@/composables/useAnnouncer';
import { useFocusTrap } from '@/composables/useFocusTrap';
import { useEscapeKey } from '@/composables/useEscapeKey';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LiveAnnouncer from '@/Components/LiveAnnouncer.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ConnectionStatus from '@/Components/ConnectionStatus.vue';
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
    ClockIcon,
    DocumentDuplicateIcon,
    ArchiveBoxIcon,
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

// Role display configuration
const roleConfig = computed(() => {
    const configs = {
        'super_admin': { label: 'System Admin', color: 'bg-purple-600', icon: KeyIcon },
        'landlord': { label: 'Landlord', color: 'bg-blue-600', icon: BuildingOffice2Icon },
        'caretaker': { label: 'Caretaker', color: 'bg-green-600', icon: WrenchScrewdriverIcon },
        'tenant': { label: 'Tenant', color: 'bg-amber-600', icon: HomeIcon },
    };
    return configs[user.value?.role] || { label: 'User', color: 'bg-gray-600', icon: UserCircleIcon };
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
            { name: 'Dashboard', href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard') },
            { name: 'Landlords', href: route('admin.landlords'), icon: BuildingOffice2Icon, active: route().current('admin.landlords*') },
            { name: 'All Users', href: route('admin.users'), icon: UserGroupIcon, active: route().current('admin.users*') },
            { name: 'Platform Billing', href: route('admin.billing.index'), icon: CurrencyDollarIcon, active: route().current('admin.billing*') },
            { name: 'System Settings', href: route('admin.settings'), icon: Cog6ToothIcon, active: route().current('admin.settings') },
        ];
    }

    const role = user.value?.role;
    if (role === 'landlord') {
        return [
            { name: 'Dashboard', href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard') },

            // PROPERTIES
            { type: 'divider', label: 'Properties' },
            { name: 'Buildings', href: route('buildings.index'), icon: HomeModernIcon, active: route().current('buildings.*') },
            { name: 'Add Property', href: route('onboarding.create'), icon: PlusCircleIcon, active: route().current('onboarding.*') },

            // TENANTS HUB (Consolidated)
            {
                name: 'Tenants',
                href: route('tenants.hub'),
                icon: UsersIcon,
                active: route().current('tenants.*') || route().current('tenant-invitations.*') || route().current('verifications.*') || route().current('payment-verifications.*') || route().current('move-outs.*'),
                badgeKey: 'tenants',
                badgeColor: 'bg-blue-500'
            },

            // FINANCES HUB (Already consolidated)
            { name: 'Finances', href: route('finances.index'), icon: BanknotesIcon, active: route().current('finances.*'), badgeKey: 'invoices', badgeColor: 'bg-red-500' },

            // MAINTENANCE HUB (Consolidated)
            {
                name: 'Maintenance',
                href: route('maintenance.hub'),
                icon: WrenchScrewdriverIcon,
                active: route().current('maintenance.*') || route().current('tickets.*') || route().current('complaints.*'),
                badgeKey: 'tickets',
                badgeColor: 'bg-orange-500'
            },

            // WATER HUB (Conditional, Consolidated)
            ...(featureAccess.value.water_billing ? [{
                name: 'Water',
                href: route('water.hub'),
                icon: BeakerIcon,
                active: route().current('water.*') || route().current('readings.*'),
                badgeKey: 'readings',
                badgeColor: 'bg-cyan-500'
            }] : []),

            // ARCHIVE HUB (Consolidated)
            {
                name: 'Archive',
                href: route('archive.hub'),
                icon: ArchiveBoxIcon,
                active: route().current('archive.*') || route().current('documents.*') || route().current('leases.index') || route().current('activity-logs.*')
            },

            // OPERATIONS HUB (Consolidated)
            {
                name: 'Operations',
                href: route('operations.hub'),
                icon: Cog6ToothIcon,
                active: route().current('operations.*') || route().current('notifications.*') || route().current('bulk.*') || route().current('invitations.*') || route().current('imports.*') || route().current('inbox.*'),
                badgeKey: 'inbox',
                badgeColor: 'bg-blue-500'
            },

            // SETTINGS
            { type: 'divider', label: '' },
            { name: 'Settings', href: route('settings.index'), icon: Cog6ToothIcon, active: route().current('settings.*') },
        ];
    }

    if (role === 'caretaker') {
        return [
            { name: 'Dashboard', href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard') },
            { name: 'My Tickets', href: route('tickets.index'), icon: TicketIcon, active: route().current('tickets.*'), badgeKey: 'tickets', badgeColor: 'bg-yellow-500' },
            ...(featureAccess.value.water_billing ? [{ name: 'Water Readings', href: route('readings.index'), icon: ClipboardDocumentListIcon, active: route().current('readings.*'), badgeKey: 'readings', badgeColor: 'bg-blue-500' }] : []),
        ];
    }

    if (role === 'tenant') {
        return [
            { name: 'Dashboard', href: route('dashboard'), icon: HomeIcon, active: route().current('dashboard') },
            { name: 'My Finances', href: route('tenant.finances.index'), icon: BanknotesIcon, active: route().current('tenant.finances.*'), badgeKey: 'invoices', badgeColor: 'bg-red-500' },
            { name: 'My Tickets', href: route('tickets.index'), icon: TicketIcon, active: route().current('tickets.*'), badgeKey: 'tickets', badgeColor: 'bg-yellow-500' },
            { name: 'My Lease', href: route('tenant.lease'), icon: DocumentTextIcon, active: route().current('tenant.lease') },
            { name: 'Notifications', href: route('tenant.notifications'), icon: BellIcon, active: route().current('tenant.notifications'), badgeKey: 'notifications', badgeColor: 'bg-indigo-500' },
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
                class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-[100] focus:rounded-md focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                Skip to main content
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
            <div v-if="isImpersonating" class="bg-yellow-500 text-yellow-900 px-4 py-2 fixed top-0 left-0 right-0 z-50">
                <div class="max-w-7xl mx-auto flex items-center justify-between">
                    <div class="flex items-center">
                        <ExclamationTriangleIcon class="h-5 w-5 mr-2" />
                        <span class="font-medium">
                            Viewing as: <strong>{{ user.name }}</strong> ({{ user.role }})
                            <span v-if="isRestricted" class="ml-2 text-red-900 font-bold">
                                — read-only (Article 18)
                            </span>
                        </span>
                    </div>
                    <button @click="stopImpersonating"
                            class="px-3 py-1 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm font-medium">
                        Stop Impersonating
                    </button>
                </div>
            </div>

            <!-- SIDEBAR (Desktop) -->
            <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200 hidden lg:flex lg:flex-col"
                   :class="{ 'top-10': isImpersonating }">

                <!-- Logo & Role Badge -->
                <div class="h-16 flex items-center justify-between px-4 border-b border-gray-200">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <ApplicationLogo class="h-8 w-auto fill-current text-gray-800" />
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
                            <p class="text-xs text-gray-500 group-hover:text-indigo-500 transition-colors">View Buildings</p>
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
                <nav class="flex-1 overflow-y-auto py-4 px-3">
                    <template v-for="(item, index) in navigationItems" :key="index">
                        <!-- Divider -->
                        <div v-if="item.type === 'divider'" class="mt-6 mb-2">
                            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ item.label }}</p>
                        </div>

                        <!-- Nav Link -->
                        <Link v-else
                              :href="item.href"
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
                                  class="ml-auto min-w-5 h-5 px-1.5 rounded-full text-xs font-bold text-white flex items-center justify-center">
                                {{ navBadges[item.badgeKey] > 99 ? '99+' : navBadges[item.badgeKey] }}
                            </span>
                        </Link>
                    </template>
                </nav>

                <!-- User Menu (Bottom) -->
                <div class="border-t border-gray-200 p-4">
                    <Dropdown align="left" width="56" :dropUp="true">
                        <template #trigger>
                            <button class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors text-left group">
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
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Account</p>
                            </div>
                            <DropdownLink :href="route('settings.index')">
                                <Cog6ToothIcon class="h-5 w-5 text-gray-400" />
                                <span>Settings</span>
                            </DropdownLink>
                            <DropdownLink :href="route('profile.edit')">
                                <UserCircleIcon class="h-5 w-5 text-gray-400" />
                                <span>My Profile</span>
                            </DropdownLink>

                            <!-- Support Section -->
                            <div class="my-1 mx-2 border-t border-gray-100"></div>
                            <div class="px-4 py-2">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Support</p>
                            </div>
                            <DropdownLink :href="route('help.index')">
                                <QuestionMarkCircleIcon class="h-5 w-5 text-gray-400" />
                                <span>Get Help</span>
                            </DropdownLink>

                            <!-- Billing Section (Landlords only) -->
                            <template v-if="user.role === 'landlord'">
                                <div class="my-1 mx-2 border-t border-gray-100"></div>
                                <div class="px-4 py-2">
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Billing</p>
                                </div>
                                <DropdownLink :href="route('finances.settings')">
                                    <BanknotesIcon class="h-5 w-5 text-gray-400" />
                                    <span>Payment Settings</span>
                                </DropdownLink>
                                <DropdownLink :href="route('invoice-settings.edit')">
                                    <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                                    <span>Invoice Settings</span>
                                </DropdownLink>
                                <DropdownLink :href="route('invoice-templates.index')">
                                    <DocumentDuplicateIcon class="h-5 w-5 text-gray-400" />
                                    <span>Invoice Templates</span>
                                </DropdownLink>
                                <DropdownLink :href="route('subscription.index')">
                                    <SparklesIcon class="h-5 w-5 text-gray-400" />
                                    <span>Subscription</span>
                                </DropdownLink>
                            </template>

                            <!-- Log Out -->
                            <div class="my-1 mx-2 border-t border-gray-100"></div>
                            <DropdownLink :href="route('logout')" method="post" as="button" :danger="true">
                                <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                                <span>Log Out</span>
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
                    aria-label="Navigation menu"
                    class="fixed inset-y-0 left-0 w-64 bg-white shadow-xl flex flex-col"
                >
                    <div class="h-16 flex items-center justify-between px-4 border-b border-gray-200">
                        <Link :href="route('dashboard')" class="flex items-center">
                            <ApplicationLogo class="h-8 w-auto fill-current text-gray-800" />
                        </Link>
                        <button @click="closeMobileSidebar" aria-label="Close navigation menu" class="p-2 rounded-md hover:bg-gray-100">
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
                                <p class="text-xs text-gray-500 group-hover:text-indigo-500">View Buildings</p>
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

                    <nav class="flex-1 overflow-y-auto py-4 px-3">
                        <template v-for="(item, index) in navigationItems" :key="index">
                            <div v-if="item.type === 'divider'" class="mt-6 mb-2">
                                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ item.label }}</p>
                            </div>
                            <Link v-else
                                  :href="item.href"
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
                                      class="ml-auto min-w-5 h-5 px-1.5 rounded-full text-xs font-bold text-white flex items-center justify-center">
                                    {{ navBadges[item.badgeKey] > 99 ? '99+' : navBadges[item.badgeKey] }}
                                </span>
                            </Link>
                        </template>
                    </nav>

                    <div class="border-t border-gray-200 p-4 space-y-1">
                        <!-- Account -->
                        <p class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Account</p>
                        <Link :href="route('settings.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <Cog6ToothIcon class="h-5 w-5 text-gray-400" />
                            <span>Settings</span>
                        </Link>
                        <Link :href="route('profile.edit')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <UserCircleIcon class="h-5 w-5 text-gray-400" />
                            <span>My Profile</span>
                        </Link>

                        <!-- Support -->
                        <div class="my-2 border-t border-gray-100"></div>
                        <p class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Support</p>
                        <Link :href="route('help.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <QuestionMarkCircleIcon class="h-5 w-5 text-gray-400" />
                            <span>Get Help</span>
                        </Link>

                        <!-- Billing (Landlords only) -->
                        <template v-if="user.role === 'landlord'">
                            <div class="my-2 border-t border-gray-100"></div>
                            <p class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Billing</p>
                            <Link :href="route('finances.settings')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <BanknotesIcon class="h-5 w-5 text-gray-400" />
                                <span>Payment Settings</span>
                            </Link>
                            <Link :href="route('invoice-settings.edit')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                                <span>Invoice Settings</span>
                            </Link>
                            <Link :href="route('invoice-templates.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <DocumentDuplicateIcon class="h-5 w-5 text-gray-400" />
                                <span>Invoice Templates</span>
                            </Link>
                            <Link :href="route('subscription.index')" @click="showMobileSidebar = false" class="flex items-center gap-3 text-sm text-gray-700 py-2.5 px-3 rounded-lg hover:bg-gray-50 transition-colors">
                                <SparklesIcon class="h-5 w-5 text-gray-400" />
                                <span>Subscription</span>
                            </Link>
                        </template>

                        <!-- Log Out -->
                        <div class="my-2 border-t border-gray-100"></div>
                        <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-3 text-sm text-red-600 py-2.5 px-3 rounded-lg hover:bg-red-50 w-full transition-colors">
                            <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                            <span>Log Out</span>
                        </Link>
                    </div>
                </aside>
            </div>

            <!-- MAIN CONTENT AREA -->
            <div class="lg:pl-64" :class="{ 'pt-10': isImpersonating }">
                <!-- Top Bar (Mobile hamburger + Sub-navigation slot) -->
                <header class="sticky top-0 z-30 bg-white border-b border-gray-200 h-16 flex items-center px-4 lg:px-8"
                        :class="{ 'top-10': isImpersonating }">
                    <!-- Mobile Menu Button -->
                    <button
                        ref="hamburgerRef"
                        @click="showMobileSidebar = true"
                        aria-label="Open navigation menu"
                        aria-controls="mobile-sidebar"
                        :aria-expanded="showMobileSidebar"
                        class="lg:hidden p-2 -ml-2 mr-2 rounded-md hover:bg-gray-100"
                    >
                        <Bars3Icon class="h-6 w-6 text-gray-500" aria-hidden="true" />
                    </button>

                    <!-- Page Header Slot (for contextual sub-navigation) -->
                    <div class="flex-1">
                        <slot name="header" />
                    </div>

                    <!-- Connection Status & Notification Bell -->
                    <div class="flex items-center gap-3">
                        <ConnectionStatus />
                        <NotificationBell />
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
                    class="bg-amber-50 border-l-4 border-amber-500 px-4 py-3"
                >
                    <div class="flex items-start gap-3">
                        <ExclamationTriangleIcon class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" aria-hidden="true" />
                        <div class="flex-1 text-sm text-amber-900">
                            <p class="font-semibold">Your account is currently restricted (Article 18).</p>
                            <p class="mt-0.5">
                                You have read-only access. Write actions (edits, payments, deletions) will be denied.
                                <Link :href="route('gdpr.index')" class="underline font-medium hover:text-amber-700">
                                    Manage your privacy settings
                                </Link>
                                to release the restriction.
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
                <main id="main-content" tabindex="-1">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
