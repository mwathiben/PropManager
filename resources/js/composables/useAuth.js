/**
 * Auth & Role Checking Composable
 * Provides consistent role checking across all Vue components
 */

import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user);
    const role = computed(() => user.value?.role);

    // Role checks
    const isLandlord = computed(() => role.value === 'landlord');
    const isCaretaker = computed(() => role.value === 'caretaker');
    const isTenant = computed(() => role.value === 'tenant');
    const isSuperAdmin = computed(() => role.value === 'super_admin');

    // Permission checks
    const canManageProperty = computed(() =>
        ['landlord', 'super_admin'].includes(role.value)
    );

    const canManageInvoices = computed(() =>
        ['landlord', 'super_admin'].includes(role.value)
    );

    const canRecordPayments = computed(() =>
        ['landlord', 'super_admin'].includes(role.value)
    );

    const canUploadDocuments = computed(() =>
        ['landlord', 'caretaker', 'super_admin'].includes(role.value)
    );

    const canDeleteDocuments = computed(() =>
        ['landlord', 'super_admin'].includes(role.value)
    );

    const canManageTickets = computed(() =>
        ['landlord', 'caretaker', 'super_admin'].includes(role.value)
    );

    const canGenerateInvoices = computed(() =>
        ['landlord', 'super_admin'].includes(role.value)
    );

    const canManageCaretakers = computed(() =>
        ['landlord', 'super_admin'].includes(role.value)
    );

    const canViewAllBuildings = computed(() =>
        ['landlord', 'caretaker', 'super_admin'].includes(role.value)
    );

    return {
        // User & role
        user,
        role,

        // Role checks
        isLandlord,
        isCaretaker,
        isTenant,
        isSuperAdmin,

        // Permission checks
        canManageProperty,
        canManageInvoices,
        canRecordPayments,
        canUploadDocuments,
        canDeleteDocuments,
        canManageTickets,
        canGenerateInvoices,
        canManageCaretakers,
        canViewAllBuildings,
    };
}
