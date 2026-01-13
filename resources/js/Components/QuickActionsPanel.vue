<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    UserGroupIcon,
    BanknotesIcon,
    BuildingOfficeIcon,
    Cog6ToothIcon,
    UserPlusIcon,
    ArrowPathIcon,
    ArrowRightOnRectangleIcon,
    CreditCardIcon,
    DocumentTextIcon,
    ArrowTrendingUpIcon,
    BeakerIcon,
    TicketIcon,
    FolderIcon,
    WrenchScrewdriverIcon,
    AdjustmentsHorizontalIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    building: Object,
    vacantUnits: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['action']);

const expandedGroups = ref(['tenant']);

const toggleGroup = (groupKey) => {
    const index = expandedGroups.value.indexOf(groupKey);
    if (index === -1) {
        expandedGroups.value.push(groupKey);
    } else {
        expandedGroups.value.splice(index, 1);
    }
};

const isExpanded = (groupKey) => expandedGroups.value.includes(groupKey);

const actionGroups = [
    {
        key: 'tenant',
        name: 'Tenant Management',
        icon: UserGroupIcon,
        color: 'text-blue-600 bg-blue-50',
        actions: [
            {
                name: 'Add Tenant',
                icon: UserPlusIcon,
                description: 'Add a new tenant to a vacant unit',
                type: 'modal',
                action: 'addTenant',
            },
            {
                name: 'Lease Renewal',
                icon: ArrowPathIcon,
                description: 'Renew expiring leases',
                route: 'bulk.index',
                params: { tab: 'leases' },
            },
            {
                name: 'Move Out',
                icon: ArrowRightOnRectangleIcon,
                description: 'Process tenant move-out',
                route: 'bulk.index',
                params: { tab: 'terminate' },
            },
        ],
    },
    {
        key: 'financial',
        name: 'Financial',
        icon: BanknotesIcon,
        color: 'text-green-600 bg-green-50',
        actions: [
            {
                name: 'Record Payment',
                icon: CreditCardIcon,
                description: 'Record a manual payment',
                type: 'modal',
                action: 'recordPayment',
            },
            {
                name: 'Generate Invoice',
                icon: DocumentTextIcon,
                description: 'Generate monthly invoices',
                route: 'invoices.generate',
                method: 'post',
            },
            {
                name: 'Rent Hike',
                icon: ArrowTrendingUpIcon,
                description: 'Adjust rent for units',
                type: 'modal',
                action: 'rentHike',
            },
        ],
    },
    {
        key: 'property',
        name: 'Property',
        icon: BuildingOfficeIcon,
        color: 'text-purple-600 bg-purple-50',
        actions: [
            {
                name: 'Water Readings',
                icon: BeakerIcon,
                description: 'Record water meter readings',
                route: 'readings.index',
            },
            {
                name: 'Create Ticket',
                icon: TicketIcon,
                description: 'Report an issue',
                route: 'tickets.create',
            },
            {
                name: 'View Documents',
                icon: FolderIcon,
                description: 'Manage building documents',
                route: 'documents.index',
            },
        ],
    },
    {
        key: 'settings',
        name: 'Settings',
        icon: Cog6ToothIcon,
        color: 'text-gray-600 bg-gray-50',
        actions: [
            {
                name: 'Configure Building',
                icon: WrenchScrewdriverIcon,
                description: 'Edit units and structure',
                route: 'buildings.edit',
                routeParam: 'building',
            },
            {
                name: 'Water Settings',
                icon: AdjustmentsHorizontalIcon,
                description: 'Configure water billing',
                route: 'buildings.water-settings',
                routeParam: 'building',
            },
        ],
    },
];

const handleAction = (action) => {
    if (action.type === 'modal') {
        emit('action', action.action);
    }
};

const getRouteHref = (action) => {
    if (action.routeParam === 'building' && props.building) {
        return route(action.route, props.building.id);
    }
    if (action.params) {
        return route(action.route, action.params);
    }
    return route(action.route);
};
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
            <h3 class="font-semibold text-gray-900">Quick Actions</h3>
        </div>

        <div class="divide-y divide-gray-100">
            <div
                v-for="group in actionGroups"
                :key="group.key"
                class="overflow-hidden"
            >
                <!-- Group Header -->
                <button
                    @click="toggleGroup(group.key)"
                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="group.color">
                            <component :is="group.icon" class="w-4 h-4" />
                        </div>
                        <span class="font-medium text-gray-900">{{ group.name }}</span>
                    </div>
                    <component
                        :is="isExpanded(group.key) ? ChevronDownIcon : ChevronRightIcon"
                        class="w-5 h-5 text-gray-400"
                    />
                </button>

                <!-- Group Actions -->
                <div
                    v-if="isExpanded(group.key)"
                    class="bg-gray-50/50 border-t border-gray-100"
                >
                    <template v-for="action in group.actions" :key="action.name">
                        <!-- Link Action -->
                        <Link
                            v-if="action.route && !action.method"
                            :href="getRouteHref(action)"
                            class="flex items-center gap-3 px-4 py-3 pl-12 hover:bg-gray-100 transition"
                        >
                            <component :is="action.icon" class="w-5 h-5 text-gray-400" />
                            <div>
                                <div class="text-sm font-medium text-gray-700">{{ action.name }}</div>
                                <div class="text-xs text-gray-500">{{ action.description }}</div>
                            </div>
                        </Link>

                        <!-- Modal Action -->
                        <button
                            v-else-if="action.type === 'modal'"
                            @click="handleAction(action)"
                            class="w-full flex items-center gap-3 px-4 py-3 pl-12 hover:bg-gray-100 transition text-left"
                        >
                            <component :is="action.icon" class="w-5 h-5 text-gray-400" />
                            <div>
                                <div class="text-sm font-medium text-gray-700">{{ action.name }}</div>
                                <div class="text-xs text-gray-500">{{ action.description }}</div>
                            </div>
                        </button>

                        <!-- POST Action (form) -->
                        <Link
                            v-else-if="action.method === 'post'"
                            :href="getRouteHref(action)"
                            method="post"
                            as="button"
                            class="w-full flex items-center gap-3 px-4 py-3 pl-12 hover:bg-gray-100 transition text-left"
                        >
                            <component :is="action.icon" class="w-5 h-5 text-gray-400" />
                            <div>
                                <div class="text-sm font-medium text-gray-700">{{ action.name }}</div>
                                <div class="text-xs text-gray-500">{{ action.description }}</div>
                            </div>
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
